<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Services\AppointmentReminderCallService;
use App\Services\TwilioSmsService;
use App\Services\TwilioPhoneNumberService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TwilioPhoneNumberController extends Controller
{
    public function assign(Request $request, TwilioPhoneNumberService $numbers): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if (! $clinic) {
            return redirect('/ajustes#numero-asignado')->with('twilio_number_error', 'No hay salon asociado al usuario.');
        }

        $clinic = $numbers->assignToClinic($clinic, true);

        if ($clinic->twilio_number_status !== 'active') {
            return redirect('/ajustes#numero-asignado')->with('twilio_number_error', $clinic->twilio_number_error ?: 'No se pudo asignar el numero.');
        }

        return redirect('/ajustes#numero-asignado')->with('twilio_number_status', 'Numero Twilio asignado correctamente.');
    }

    public function incoming(Request $request): Response
    {
        $from = (string) $request->input('From');
        $to = (string) $request->input('To');
        $callSid = (string) $request->input('CallSid');

        $clinic = $this->findClinicByPhone($to, $from);
        $client = $clinic ? $this->findClientByPhone($clinic, $from) : null;
        $appointment = $client ? $this->nextAppointment($client) : null;

        $message = $this->messageForCall($clinic, $client, $appointment);

        DB::table('call_logs')->insert([
            'clinic_id' => $clinic?->id,
            'client_id' => $client?->id,
            'appointment_id' => $appointment?->id,
            'twilio_call_sid' => $callSid ?: null,
            'from_phone' => $from ?: 'unknown',
            'to_phone' => $to ?: null,
            'status' => $appointment ? 'resolved' : 'received',
            'intent' => 'appointment_lookup',
            'transcript' => $message,
            'metadata' => json_encode([
                'matched_clinic' => (bool) $clinic,
                'matched_client' => (bool) $client,
                'matched_appointment' => (bool) $appointment,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .$this->say($message)
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    public function reminder(Appointment $appointment, string $token, AppointmentReminderCallService $reminders): Response
    {
        if (! $reminders->validToken($appointment, $token)) {
            return $this->twiml('No pudimos validar esta llamada. Por favor contacta al salon.');
        }

        $message = $reminders->messageFor($appointment);
        $action = route('twilio.voice.reminder-choice', [$appointment, $token]);

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Gather numDigits="1" timeout="8" action="'.htmlspecialchars($action, ENT_XML1).'" method="POST">'
            .$this->say($message)
            .'</Gather>'
            .$this->say('No recibimos ninguna opcion. Gracias.')
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    public function reminderChoice(Request $request, Appointment $appointment, string $token, AppointmentReminderCallService $reminders, TwilioSmsService $sms): Response
    {
        if (! $reminders->validToken($appointment, $token)) {
            return $this->twiml('No pudimos validar esta llamada. Por favor contacta al salon.');
        }

        $appointment->loadMissing(['clinic', 'client']);
        $digits = (string) $request->input('Digits');

        if ($digits !== '1') {
            return $this->twiml('Gracias. Tu cita se mantiene confirmada.');
        }

        $link = route('public-reschedule.show', [$appointment, $token]);
        $body = ($appointment->clinic?->name ?? 'Secretaria Virtual').": usa este enlace para reagendar tu cita: {$link}";
        $providerMessageId = null;
        $status = 'sent';
        $error = null;

        if (! ($appointment->clinic?->notificationEnabled('appointment_reschedule_link_sms') ?? true)) {
            $status = 'skipped';
            $error = 'SMS de enlace para reagendar desactivado en ajustes.';
        } else {
            try {
                $providerMessageId = $sms->send($appointment->client?->phone, $body);

                if (! $providerMessageId) {
                    $status = 'failed';
                    $error = 'Twilio SMS no esta configurado o el telefono no es valido.';
                }
            } catch (\Throwable $exception) {
                $status = 'failed';
                $error = $exception->getMessage();
            }
        }

        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'sms',
            'event' => 'appointment_reschedule_link',
            'recipient' => $appointment->client?->phone ?? 'unknown',
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'body' => $body,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->twiml($status === 'sent'
            ? 'Te enviamos un mensaje de texto con el enlace para reagendar tu cita. Gracias.'
            : 'No pudimos enviar el mensaje de texto. Por favor contacta al salon.');
    }

    public function reminderStatus(Request $request): Response
    {
        $callSid = (string) $request->input('CallSid');
        $callStatus = (string) $request->input('CallStatus') ?: 'callback';

        if ($callSid) {
            DB::table('notifications')
                ->where('provider_message_id', $callSid)
                ->where('event', 'appointment_reminder_call')
                ->update([
                    'status' => in_array($callStatus, ['completed', 'answered'], true) ? 'sent' : $callStatus,
                    'updated_at' => now(),
                ]);
        }

        DB::table('call_logs')->insert([
            'clinic_id' => null,
            'client_id' => null,
            'appointment_id' => null,
            'twilio_call_sid' => $callSid ?: null,
            'from_phone' => (string) $request->input('From') ?: 'unknown',
            'to_phone' => (string) $request->input('To') ?: null,
            'status' => $callStatus,
            'intent' => 'appointment_reminder_call',
            'transcript' => null,
            'metadata' => json_encode($request->all()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response('OK');
    }

    private function findClinicByPhone(string $phone, string $callerPhone = ''): ?Clinic
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return null;
        }

        $clinic = Clinic::query()
            ->whereNotNull('twilio_phone_number')
            ->get()
            ->first(fn (Clinic $clinic) => $this->samePhone($clinic->twilio_phone_number, $normalized));

        if ($clinic || ! $this->samePhone(config('services.twilio.from'), $normalized)) {
            return $clinic;
        }

        $caller = $this->normalizePhone($callerPhone);

        if ($caller === '') {
            return null;
        }

        $clinicIds = Client::query()
            ->whereNotNull('phone')
            ->get(['clinic_id', 'phone'])
            ->filter(fn (Client $client) => $this->samePhone($client->phone, $caller))
            ->pluck('clinic_id')
            ->unique()
            ->values();

        return $clinicIds->count() === 1
            ? Clinic::query()->find($clinicIds->first())
            : null;
    }

    private function findClientByPhone(Clinic $clinic, string $phone): ?Client
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return null;
        }

        return Client::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotNull('phone')
            ->get()
            ->first(fn (Client $client) => $this->samePhone($client->phone, $normalized));
    }

    private function nextAppointment(Client $client): ?Appointment
    {
        return Appointment::query()
            ->with(['service', 'stylist'])
            ->where('clinic_id', $client->clinic_id)
            ->where('client_id', $client->id)
            ->where('starts_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('starts_at')
            ->first();
    }

    private function messageForCall(?Clinic $clinic, ?Client $client, ?Appointment $appointment): string
    {
        if (! $clinic) {
            return 'Hola, gracias por llamar. No pude identificar la linea de este salon. Por favor intenta de nuevo mas tarde.';
        }

        if (! $client) {
            return 'Hola, gracias por llamar a '.$clinic->name.'. No encontre una cita asociada a este numero de telefono. Por favor llama al salon para que podamos ayudarte.';
        }

        if (! $appointment) {
            return 'Hola '.$this->spokenName($client).'. No encontre una cita proxima asociada a tu numero. Por favor llama al salon para que podamos ayudarte.';
        }

        return 'Hola '.$this->spokenName($client).', acabo de ver que tienes una cita '.$this->spokenDateTime($appointment->starts_at, $clinic).'.';
    }

    private function spokenName(Client $client): string
    {
        return trim($client->first_name) ?: 'cliente';
    }

    private function spokenDateTime(CarbonInterface $date, Clinic $clinic): string
    {
        $startsAt = $date->copy()->timezone($clinic->localTimezone());
        $hour = $startsAt->format('g');
        $minutes = $startsAt->format('i');
        $time = $minutes === '00' ? $hour : $hour.' y '.$minutes;
        $meridiem = $startsAt->format('A') === 'AM' ? 'de la manana' : 'de la tarde';

        if ($startsAt->isToday()) {
            return 'hoy a las '.$time.' '.$meridiem;
        }

        if ($startsAt->isTomorrow()) {
            return 'manana a las '.$time.' '.$meridiem;
        }

        $days = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return 'el '.$days[(int) $startsAt->format('w')].' '.$startsAt->format('j').' de '.$months[(int) $startsAt->format('n')].' a las '.$time.' '.$meridiem;
    }

    private function samePhone(?string $storedPhone, string $normalizedPhone): bool
    {
        $stored = $this->normalizePhone((string) $storedPhone);

        if ($stored === '') {
            return false;
        }

        return $stored === $normalizedPhone
            || substr($stored, -10) === substr($normalizedPhone, -10);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }

    private function twiml(string $message): Response
    {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .$this->say($message)
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function say(string $message): string
    {
        return '<Say language="es-ES" voice="Polly.Conchita">'
            .htmlspecialchars($message, ENT_XML1)
            .'</Say>';
    }
}
