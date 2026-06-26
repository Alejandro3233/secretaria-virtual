<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Services\AppointmentReminderCallService;
use App\Services\AppointmentService;
use App\Services\GoogleTextToSpeechService;
use App\Services\NoraLanguageService;
use App\Services\ClinicResolver;
use App\Services\TwilioSmsService;
use App\Services\TwilioPhoneNumberService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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

    public function incoming(Request $request, AppointmentReminderCallService $reminders): Response
    {
        $from = (string) $request->input('From');
        $to = (string) $request->input('To');
        $callSid = (string) $request->input('CallSid');

        $clinic = $this->findClinicByPhone($to, $from);
        $client = $clinic ? $this->findClientByPhone($clinic, $from) : null;
        $appointment = $client ? $this->nextAppointment($client) : null;

        $message = $this->messageForCall($clinic, $client, $appointment);
        $browserVoiceEnabled = $clinic && $this->browserVoiceConfigured();

        DB::table('call_logs')->insert([
            'clinic_id' => $clinic?->id,
            'client_id' => $client?->id,
            'appointment_id' => $appointment?->id,
            'twilio_call_sid' => $callSid ?: null,
            'from_phone' => $from ?: 'unknown',
            'to_phone' => $to ?: null,
            'status' => $browserVoiceEnabled ? 'ringing' : 'received',
            'intent' => 'appointment_lookup',
            'transcript' => $message,
            'metadata' => json_encode([
                'matched_clinic' => (bool) $clinic,
                'matched_client' => (bool) $client,
                'matched_appointment' => (bool) $appointment,
                'handled_by' => $browserVoiceEnabled ? 'pending' : 'nora',
                'handled_by_name' => $browserVoiceEnabled ? null : 'Nora',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($browserVoiceEnabled) {
            $fallback = route('twilio.voice.incoming-fallback');
            $statusCallback = route('twilio.voice.browser-status', ['parent_call_sid' => $callSid]);
            $identity = $this->browserIdentity($clinic);
            $timeout = max(5, min(60, (int) config('services.twilio.browser_ring_timeout', 18)));

            $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
                .'<Dial answerOnBridge="true" timeout="'.$timeout.'" action="'.htmlspecialchars($fallback, ENT_XML1).'" method="POST">'
                .'<Client statusCallback="'.htmlspecialchars($statusCallback, ENT_XML1).'" statusCallbackMethod="POST" statusCallbackEvent="initiated ringing answered completed">'
                .'<Identity>'.htmlspecialchars($identity, ENT_XML1).'</Identity>'
                .'<Parameter name="callerName" value="'.htmlspecialchars($client ? trim($client->first_name.' '.$client->last_name) : 'Cliente', ENT_XML1).'" />'
                .'<Parameter name="callerPhone" value="'.htmlspecialchars($from, ENT_XML1).'" />'
                .'<Parameter name="parentCallSid" value="'.htmlspecialchars($callSid, ENT_XML1).'" />'
                .'</Client></Dial></Response>';

            return response($twiml, 200, ['Content-Type' => 'text/xml']);
        }

        return $this->assistantResponse($message, $clinic, $appointment, $reminders);
    }

    public function incomingFallback(Request $request, AppointmentReminderCallService $reminders): Response
    {
        $callSid = (string) $request->input('CallSid');
        $dialStatus = (string) $request->input('DialCallStatus');

        if ($dialStatus === 'completed') {
            $this->completeIncomingCall($callSid);

            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
        }

        $log = DB::table('call_logs')->where('twilio_call_sid', $callSid)->latest('id')->first();
        $clinic = $log?->clinic_id ? Clinic::query()->find($log->clinic_id) : null;
        $appointment = $log?->appointment_id ? Appointment::query()->find($log->appointment_id) : null;

        $this->updateIncomingCallState($callSid, 'in-progress', 'nora', 'Nora');

        return $this->assistantResponse(
            $log?->transcript ?: $this->messageForCall($clinic, null, $appointment),
            $clinic,
            $appointment,
            $reminders,
        );
    }

    public function browserStatus(Request $request): Response
    {
        $callSid = (string) ($request->input('parent_call_sid') ?: $request->input('ParentCallSid') ?: $request->input('CallSid'));
        $status = match ((string) $request->input('CallStatus')) {
            'answered', 'in-progress' => 'answered',
            'completed' => 'completed',
            'initiated', 'ringing' => 'ringing',
            default => null,
        };

        if ($callSid !== '' && $status) {
            $log = DB::table('call_logs')->where('twilio_call_sid', $callSid)->latest('id')->first();
            $clinicName = $log?->clinic_id
                ? Clinic::query()->whereKey($log->clinic_id)->value('name')
                : null;

            $this->updateIncomingCallState(
                $callSid,
                $status,
                $status === 'answered' ? 'salon' : null,
                $status === 'answered' ? ($clinicName ?: 'el salón') : null,
            );
        }

        return response('OK');
    }

    public function browserToken(Request $request, ClinicResolver $clinics): JsonResponse
    {
        if (! $this->browserVoiceConfigured()) {
            return response()->json([
                'configured' => false,
                'message' => 'Faltan TWILIO_API_KEY_SID, TWILIO_API_KEY_SECRET o TWILIO_TWIML_APP_SID.',
            ], 503);
        }

        $clinic = $clinics->currentOrCreate($request->user());
        $token = $this->voiceAccessToken($this->browserIdentity($clinic));

        return response()->json([
            'configured' => true,
            'token' => $token,
            'identity' => $this->browserIdentity($clinic),
        ]);
    }

    private function assistantResponse(string $message, ?Clinic $clinic, ?Appointment $appointment, AppointmentReminderCallService $reminders): Response
    {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>';

        if ($appointment) {
            $action = route('twilio.voice.reminder-choice', [$appointment, $reminders->tokenFor($appointment)]);

            $twiml .= '<Gather numDigits="1" timeout="8" action="'.htmlspecialchars($action, ENT_XML1).'" method="POST">'
                .$this->say($message, $clinic)
                .'</Gather>'
                .$this->say($this->language()->text($clinic, 'thanks'), $clinic);
        } else {
            $twiml .= $this->say($message, $clinic);
        }

        $twiml .= '<Redirect method="POST">'.htmlspecialchars(route('twilio.voice.incoming-complete'), ENT_XML1).'</Redirect>';
        $twiml .= '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function browserVoiceConfigured(): bool
    {
        return collect([
            config('services.twilio.account_sid'),
            config('services.twilio.api_key_sid'),
            config('services.twilio.api_key_secret'),
            config('services.twilio.twiml_app_sid'),
        ])->every(fn ($value) => is_string($value) && $value !== '');
    }

    private function browserIdentity(Clinic $clinic): string
    {
        return 'clinic-'.$clinic->id;
    }

    private function voiceAccessToken(string $identity): string
    {
        $now = time();
        $apiKey = (string) config('services.twilio.api_key_sid');
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
            'cty' => 'twilio-fpa;v=1',
        ];
        $payload = [
            'jti' => $apiKey.'-'.$now,
            'grants' => [
                'identity' => $identity,
                'voice' => [
                    'incoming' => ['allow' => true],
                    'outgoing' => ['application_sid' => (string) config('services.twilio.twiml_app_sid')],
                ],
            ],
            'iat' => $now,
            'exp' => $now + 3600,
            'iss' => $apiKey,
            'sub' => (string) config('services.twilio.account_sid'),
        ];

        $segments = [
            $this->base64Url(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), (string) config('services.twilio.api_key_secret'), true);
        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function reminder(Appointment $appointment, string $token, AppointmentReminderCallService $reminders): Response
    {
        $appointment->loadMissing('clinic');

        if (! $reminders->validToken($appointment, $token)) {
            return $this->twiml($this->language()->text($appointment->clinic, 'invalid'), $appointment->clinic);
        }

        $message = $reminders->messageFor($appointment);
        $action = route('twilio.voice.reminder-choice', [$appointment, $token]);

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Gather numDigits="1" timeout="8" action="'.htmlspecialchars($action, ENT_XML1).'" method="POST">'
            .$this->say($message, $appointment->clinic)
            .'</Gather>'
            .$this->say($this->language()->text($appointment->clinic, 'see_you'), $appointment->clinic)
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    public function reminderChoice(Request $request, Appointment $appointment, string $token, AppointmentReminderCallService $reminders, TwilioSmsService $sms): Response
    {
        $this->completeIncomingCall((string) $request->input('CallSid'));
        $appointment->loadMissing(['clinic', 'client']);

        if (! $reminders->validToken($appointment, $token)) {
            return $this->twiml($this->language()->text($appointment->clinic, 'invalid'), $appointment->clinic);
        }

        $digits = (string) $request->input('Digits');

        if ($digits !== '1') {
            return $this->twiml($this->language()->text($appointment->clinic, 'kept'), $appointment->clinic);
        }

        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'voice',
            'event' => 'appointment_client_response',
            'recipient' => $appointment->client?->phone ?? 'unknown',
            'status' => 'received',
            'body' => 'modify',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = route('public-reschedule.show', [$appointment, $token]);
        $body = $this->language()->text($appointment->clinic, 'reschedule_sms', [
            'clinic' => $appointment->clinic?->name ?? 'Secretary365',
            'link' => $link,
        ]);
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

        return $this->twiml(
            $this->language()->text($appointment->clinic, $status === 'sent' ? 'sms_sent' : 'sms_failed'),
            $appointment->clinic,
        );
    }

    public function incomingComplete(Request $request): Response
    {
        $this->completeIncomingCall((string) $request->input('CallSid'));

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }

    public function reminderStatus(Request $request, AppointmentService $appointments): Response
    {
        $callSid = (string) $request->input('CallSid');
        $callStatus = (string) $request->input('CallStatus') ?: 'callback';
        $notification = null;

        if ($callSid) {
            $notification = DB::table('notifications')
                ->where('provider_message_id', $callSid)
                ->where('event', 'appointment_reminder_call')
                ->orderByDesc('created_at')
                ->first();

            DB::table('notifications')
                ->where('provider_message_id', $callSid)
                ->where('event', 'appointment_reminder_call')
                ->update([
                    'status' => $callStatus,
                    'sent_at' => in_array($callStatus, ['completed', 'answered', 'in-progress'], true) ? now() : ($notification?->sent_at ?? null),
                    'updated_at' => now(),
                ]);

            if ($notification?->appointment_id && in_array($callStatus, ['answered', 'in-progress', 'completed'], true)) {
                $appointment = Appointment::query()
                    ->with(['clinic', 'client'])
                    ->whereKey($notification->appointment_id)
                    ->first();

                if ($appointment && ! in_array($appointment->status, ['cancelled', 'canceled'], true)) {
                    if ($appointment->status !== 'confirmed') {
                        $appointments->confirm($appointment);
                    }

                    $appointment->forceFill(['reminder_call_enabled' => false])->save();
                    $this->recordVoiceClientResponse($appointment, 'confirm');
                }
            }
        }

        DB::table('call_logs')->insert([
            'clinic_id' => $notification?->clinic_id,
            'client_id' => $notification?->client_id,
            'appointment_id' => $notification?->appointment_id,
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

    private function recordVoiceClientResponse(Appointment $appointment, string $response): void
    {
        $alreadyRecorded = DB::table('notifications')
            ->where('appointment_id', $appointment->id)
            ->where('event', 'appointment_client_response')
            ->where('body', $response)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'voice',
            'event' => 'appointment_client_response',
            'recipient' => $appointment->client?->phone ?? 'unknown',
            'status' => 'received',
            'body' => $response,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function completeIncomingCall(string $callSid): void
    {
        if ($callSid === '') {
            return;
        }

        DB::table('call_logs')
            ->where('twilio_call_sid', $callSid)
            ->whereIn('status', ['received', 'queued', 'initiated', 'ringing', 'in-progress', 'answered'])
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
    }

    private function updateIncomingCallState(
        string $callSid,
        string $status,
        ?string $handledBy = null,
        ?string $handledByName = null,
    ): void {
        if ($callSid === '') {
            return;
        }

        DB::table('call_logs')
            ->where('twilio_call_sid', $callSid)
            ->get(['id', 'metadata'])
            ->each(function (object $log) use ($status, $handledBy, $handledByName): void {
                $metadata = json_decode((string) $log->metadata, true);
                $metadata = is_array($metadata) ? $metadata : [];

                if ($handledBy !== null) {
                    $metadata['handled_by'] = $handledBy;
                    $metadata['handled_by_name'] = $handledByName;
                }

                DB::table('call_logs')->where('id', $log->id)->update([
                    'status' => $status,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            });
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
            return $this->language()->text(null, 'unknown_clinic');
        }

        if (! $client) {
            return $this->language()->text($clinic, 'unknown_client', ['clinic' => $clinic->name]);
        }

        if (! $appointment) {
            return $this->language()->text($clinic, 'no_appointment', [
                'clinic' => $clinic->name, 'name' => $this->spokenName($client),
            ]);
        }

        return $this->language()->text($clinic, 'appointment', [
            'clinic' => $clinic->name,
            'name' => $this->spokenName($client),
            'datetime' => $this->language()->dateTime($appointment->starts_at, $clinic),
        ]);
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
        $meridiem = $startsAt->format('A') === 'AM' ? 'de la mañana' : 'de la tarde';

        if ($startsAt->isToday()) {
            return 'hoy a las '.$time.' '.$meridiem;
        }

        if ($startsAt->isTomorrow()) {
            return 'mañana a las '.$time.' '.$meridiem;
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

    private function twiml(string $message, ?Clinic $clinic = null): Response
    {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .$this->say($message, $clinic)
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function say(string $message, ?Clinic $clinic = null): string
    {
        $voice = app(GoogleTextToSpeechService::class)->twilioSayAttributes(
            $clinic?->google_tts_voice,
            $this->language()->language($clinic),
        );

        return '<Say language="'.htmlspecialchars($voice['language'], ENT_XML1).'" voice="'.htmlspecialchars($voice['voice'], ENT_XML1).'">'
            .htmlspecialchars($message, ENT_XML1)
            .'</Say>';
    }

    private function language(): NoraLanguageService
    {
        return app(NoraLanguageService::class);
    }
}
