<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AppointmentReminderCallService
{
    public const EVENT = 'appointment_reminder_call';
    public const SMS_EVENT = 'appointment_reminder_sms';

    public function __construct(private readonly TwilioSmsService $sms)
    {
    }

    public function dueAppointments()
    {
        $query = Appointment::query()
            ->with(['clinic', 'client', 'service', 'stylist'])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [now(), now()->addHours(169)])
            ->whereHas('client', fn ($query) => $query->whereNotNull('phone'))
            ->whereDoesntHave('client', fn ($query) => $query->where('phone', 'like', 'google:%'))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('notifications')
                    ->whereColumn('notifications.appointment_id', 'appointments.id')
                    ->where('notifications.event', self::EVENT)
                    ->whereIn('notifications.status', ['sent', 'queued']);
            })
            ->orderBy('starts_at');

        if (Schema::hasColumn('appointments', 'reminder_call_enabled')) {
            $query->where('reminder_call_enabled', true);
        }

        return $query->get()
            ->filter(fn (Appointment $appointment): bool => $appointment->clinic?->notificationEnabled('appointment_reminder_call') ?? true)
            ->filter(fn (Appointment $appointment): bool => $this->insideReminderWindow($appointment, 'call'))
            ->values();
    }

    public function dueSmsAppointments()
    {
        $query = Appointment::query()
            ->with(['clinic', 'client', 'service', 'stylist'])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [now(), now()->addHours(169)])
            ->whereHas('client', fn ($query) => $query->whereNotNull('phone'))
            ->whereDoesntHave('client', fn ($query) => $query->where('phone', 'like', 'google:%'))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('notifications')
                    ->whereColumn('notifications.appointment_id', 'appointments.id')
                    ->where('notifications.event', self::SMS_EVENT)
                    ->whereIn('notifications.status', ['sent', 'queued']);
            })
            ->orderBy('starts_at');

        if (Schema::hasColumn('appointments', 'reminder_sms_enabled')) {
            $query->where('reminder_sms_enabled', true);
        }

        return $query->get()
            ->filter(fn (Appointment $appointment): bool => $appointment->clinic?->notificationEnabled('appointment_reminder_sms') ?? true)
            ->filter(fn (Appointment $appointment): bool => $this->insideReminderWindow($appointment, 'sms'))
            ->values();
    }

    private function insideReminderWindow(Appointment $appointment, string $channel): bool
    {
        $hoursBefore = $appointment->clinic?->reminderHoursBefore($channel) ?? 24;
        $startsAt = $appointment->starts_at;

        return $startsAt->between(
            now()->addHours($hoursBefore - 1),
            now()->addHours($hoursBefore + 1),
        );
    }

    public function call(Appointment $appointment): ?string
    {
        $appointment->loadMissing(['clinic', 'client']);

        if (! ($appointment->clinic?->notificationEnabled('appointment_reminder_call') ?? true)) {
            return null;
        }

        $to = $this->normalizePhone($appointment->client?->phone);
        $from = $this->normalizePhone($appointment->clinic?->twilio_phone_number ?: config('services.twilio.from'));

        if (! $to || ! $from || ! config('services.twilio.account_sid') || ! config('services.twilio.auth_token')) {
            $this->record($appointment, 'failed', null, 'Faltan datos de Twilio o telefono del cliente.');

            return null;
        }

        $url = route('twilio.voice.reminder', [
            'appointment' => $appointment->id,
            'token' => $this->tokenFor($appointment),
        ]);

        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.twilio.account_sid'), config('services.twilio.auth_token'))
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid').'/Calls.json', [
                    'To' => $to,
                    'From' => $from,
                    'Url' => $url,
                    'Method' => 'POST',
                    'StatusCallback' => route('twilio.voice.reminder-status'),
                    'StatusCallbackMethod' => 'POST',
                    'StatusCallbackEvent[0]' => 'completed',
                    'StatusCallbackEvent[1]' => 'answered',
                    'StatusCallbackEvent[2]' => 'no-answer',
                ]);

            if ($response->failed()) {
                throw new RuntimeException($response->json('message') ?: 'Twilio no pudo crear la llamada.');
            }

            $sid = $response->json('sid');
            $this->record($appointment, 'queued', $sid);

            return $sid;
        } catch (\Throwable $exception) {
            $this->record($appointment, 'failed', null, $exception->getMessage());
            Log::warning('No se pudo crear llamada recordatorio.', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function messageFor(Appointment $appointment): string
    {
        $appointment->loadMissing(['clinic', 'client']);
        $clientName = trim((string) $appointment->client?->first_name) ?: 'cliente';
        $clinicName = $appointment->clinic?->name ?? 'tu salon';
        $time = $appointment->starts_at
            ->copy()
            ->timezone($appointment->clinic?->localTimezone() ?: config('app.timezone'))
            ->format('g:i A');

        return "Hola {$clientName}, te llamamos de {$clinicName} para recordarte que tienes una cita para manana a las {$time}. Si quieres reagendar la cita marca 1.";
    }

    public function smsMessageFor(Appointment $appointment): string
    {
        $appointment->loadMissing(['clinic', 'client']);
        $clientName = trim((string) $appointment->client?->first_name) ?: 'cliente';
        $clinicName = $appointment->clinic?->name ?? 'tu salon';
        $time = $appointment->starts_at
            ->copy()
            ->timezone($appointment->clinic?->localTimezone() ?: config('app.timezone'))
            ->format('g:i A');
        $rescheduleUrl = route('public-reschedule.show', [
            'appointment' => $appointment->id,
            'token' => $this->tokenFor($appointment),
        ]);

        return "Hola {$clientName}, te recordamos que tienes una cita en {$clinicName} manana a las {$time}. Para reagendar: {$rescheduleUrl}";
    }

    public function sendSms(Appointment $appointment): ?string
    {
        $appointment->loadMissing(['clinic', 'client']);

        if (! ($appointment->clinic?->notificationEnabled('appointment_reminder_sms') ?? true)) {
            return null;
        }

        try {
            $sid = $this->sms->send((string) $appointment->client?->phone, $this->smsMessageFor($appointment));
            $this->recordSms($appointment, $sid ? 'sent' : 'failed', $sid, $sid ? null : 'Faltan datos de Twilio o telefono del cliente.');

            return $sid;
        } catch (\Throwable $exception) {
            $this->recordSms($appointment, 'failed', null, $exception->getMessage());
            Log::warning('No se pudo enviar SMS recordatorio.', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function tokenFor(Appointment $appointment): string
    {
        return hash_hmac('sha256', $appointment->id.'|'.$appointment->client_id, config('app.key'));
    }

    public function validToken(Appointment $appointment, string $token): bool
    {
        $legacyToken = hash_hmac('sha256', $appointment->id.'|'.$appointment->client_id.'|'.$appointment->starts_at?->timestamp, config('app.key'));

        return hash_equals($this->tokenFor($appointment), $token)
            || hash_equals($legacyToken, $token)
            || $this->tokenWasSentToClient($appointment, $token);
    }

    private function tokenWasSentToClient(Appointment $appointment, string $token): bool
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }

        return DB::table('notifications')
            ->where('appointment_id', $appointment->id)
            ->where('client_id', $appointment->client_id)
            ->whereIn('channel', ['email', 'sms'])
            ->where('body', 'like', '%'.$token.'%')
            ->exists();
    }

    private function record(Appointment $appointment, string $status, ?string $providerMessageId = null, ?string $error = null): void
    {
        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'voice',
            'event' => self::EVENT,
            'recipient' => $appointment->client?->phone ?? 'unknown',
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'body' => $this->messageFor($appointment),
            'error' => $error,
            'sent_at' => in_array($status, ['sent', 'queued'], true) ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordSms(Appointment $appointment, string $status, ?string $providerMessageId = null, ?string $error = null): void
    {
        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'sms',
            'event' => self::SMS_EVENT,
            'recipient' => $appointment->client?->phone ?? 'unknown',
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'body' => $this->smsMessageFor($appointment),
            'error' => $error,
            'sent_at' => in_array($status, ['sent', 'queued'], true) ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone || str_starts_with($phone, 'google:')) {
            return null;
        }

        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return preg_replace('/[^\d+]/', '', $phone);
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) >= 11) {
            return '+'.$digits;
        }

        return null;
    }
}
