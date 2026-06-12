<?php

namespace App\Services;

use App\Models\Appointment;
use App\Services\AppointmentReminderCallService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppointmentNotificationService
{
    public function __construct(
        private readonly TwilioSmsService $sms,
        private readonly AppointmentReminderCallService $reminders,
    )
    {
    }

    public function appointmentCreated(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'client', 'service', 'stylist']);
        $client = $appointment->client;

        if (! $client) {
            return;
        }

        if ($this->enabled($appointment, 'appointment_created_sms') && in_array($client->notification_preference, ['sms', 'both'], true)) {
            $smsBody = $this->newAppointmentMessage($appointment);

            try {
                $providerMessageId = $this->sms->send($client->phone, $smsBody);
                $this->recordNotification($appointment, 'sms', 'appointment_created', $client->phone, 'sent', $smsBody, $providerMessageId);
            } catch (\Throwable $exception) {
                $this->recordNotification($appointment, 'sms', 'appointment_created', $client->phone, 'failed', $smsBody, null, $exception->getMessage());
                Log::warning('No se pudo enviar SMS de nueva cita.', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->enabled($appointment, 'appointment_created_email') && $client->email && in_array($client->notification_preference, ['email', 'both'], true)) {
            $emailBody = $this->newAppointmentEmailBody($appointment);
            $emailHtml = $this->newAppointmentEmailHtml($appointment);

            try {
                Mail::html($emailHtml, function ($message) use ($appointment, $client) {
                    $message
                        ->to($client->email)
                        ->subject('Tu cita fue creada - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));
                });
                $this->recordNotification($appointment, 'email', 'appointment_created', $client->email, 'sent', $emailBody);
            } catch (\Throwable $exception) {
                $this->recordNotification($appointment, 'email', 'appointment_created', $client->email, 'failed', $emailBody, null, $exception->getMessage());
                Log::warning('No se pudo enviar email de nueva cita.', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function appointmentUpdated(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'client', 'service', 'stylist']);
        $client = $appointment->client;

        if (! $client) {
            return;
        }

        if ($this->enabled($appointment, 'appointment_updated_sms') && in_array($client->notification_preference, ['sms', 'both'], true)) {
            $smsBody = $this->updatedAppointmentMessage($appointment);

            try {
                $providerMessageId = $this->sms->send($client->phone, $smsBody);
                $this->recordNotification($appointment, 'sms', 'appointment_updated', $client->phone, 'sent', $smsBody, $providerMessageId);
            } catch (\Throwable $exception) {
                $this->recordNotification($appointment, 'sms', 'appointment_updated', $client->phone, 'failed', $smsBody, null, $exception->getMessage());
                Log::warning('No se pudo enviar SMS de cita modificada.', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->enabled($appointment, 'appointment_updated_email') && $client->email && in_array($client->notification_preference, ['email', 'both'], true)) {
            $emailBody = $this->updatedAppointmentEmailBody($appointment);

            try {
                Mail::raw($emailBody, function ($message) use ($appointment, $client) {
                    $message
                        ->to($client->email)
                        ->subject('Cambio en tu cita - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));
                });
                $this->recordNotification($appointment, 'email', 'appointment_updated', $client->email, 'sent', $emailBody);
            } catch (\Throwable $exception) {
                $this->recordNotification($appointment, 'email', 'appointment_updated', $client->email, 'failed', $emailBody, null, $exception->getMessage());
                Log::warning('No se pudo enviar email de cita modificada.', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function appointmentReconfirmed(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'client', 'service', 'stylist']);
        $client = $appointment->client;

        if (! $client?->email || ! in_array($client->notification_preference, ['email', 'both'], true)) {
            return;
        }

        $emailBody = $this->reconfirmedAppointmentEmailBody($appointment);

        try {
            Mail::raw($emailBody, function ($message) use ($appointment, $client) {
                $message
                    ->to($client->email)
                    ->subject('Tu cita fue reagendada - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));
            });
            $this->recordNotification($appointment, 'email', 'appointment_reconfirmed', $client->email, 'sent', $emailBody);
        } catch (\Throwable $exception) {
            $this->recordNotification($appointment, 'email', 'appointment_reconfirmed', $client->email, 'failed', $emailBody, null, $exception->getMessage());
            Log::warning('No se pudo enviar email de cita reagendada.', [
                'appointment_id' => $appointment->id,
                'client_id' => $client->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function newAppointmentMessage(Appointment $appointment): string
    {
        $clinicName = $appointment->clinic?->name ?? 'Secretaria Virtual';
        $serviceName = $appointment->service?->name ?? $appointment->reason ?? 'tu cita';
        $startsAt = $appointment->starts_at->format('m/d/Y g:i A');
        $stylist = $appointment->stylist?->name;

        $message = "{$clinicName}: confirmamos {$serviceName} para {$startsAt}";

        if ($stylist) {
            $message .= " con {$stylist}";
        }

        return $message.'. Responde si necesitas cambiarla.';
    }

    private function newAppointmentEmailBody(Appointment $appointment): string
    {
        $clinicName = $appointment->clinic?->name ?? 'Secretaria Virtual';
        $clientName = trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? ''));
        $serviceName = $appointment->service?->name ?? $appointment->reason ?? 'Cita';
        $timezone = $appointment->clinic?->localTimezone() ?: config('app.timezone');
        $startsAt = $appointment->starts_at->copy()->timezone($timezone)->format('m/d/Y g:i A');
        $endsAt = $appointment->ends_at?->copy()->timezone($timezone)->format('g:i A');
        $stylist = $appointment->stylist?->name ?? 'Por asignar';
        $links = $this->appointmentActionLinks($appointment);

        return implode("\n", array_filter([
            'Hola '.($clientName ?: 'cliente').',',
            '',
            "Tu cita en {$clinicName} fue creada.",
            '',
            "Servicio: {$serviceName}",
            "Fecha y hora: {$startsAt}".($endsAt ? " - {$endsAt}" : ''),
            "Estilista: {$stylist}",
            '',
            'Puedes confirmar, modificar o cancelar tu cita aqui:',
            'Confirmar: '.$links['confirm'],
            'Modificar: '.$links['reschedule'],
            'Cancelar: '.$links['cancel'],
            '',
            'Gracias.',
        ]));
    }

    private function newAppointmentEmailHtml(Appointment $appointment): string
    {
        $clinicName = e($appointment->clinic?->name ?? 'Secretaria Virtual');
        $clientName = e(trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')) ?: 'cliente');
        $serviceName = e($appointment->service?->name ?? $appointment->reason ?? 'Cita');
        $timezone = $appointment->clinic?->localTimezone() ?: config('app.timezone');
        $startsAt = e($appointment->starts_at->copy()->timezone($timezone)->format('m/d/Y g:i A'));
        $endsAt = $appointment->ends_at?->copy()->timezone($timezone)->format('g:i A');
        $stylist = e($appointment->stylist?->name ?? 'Por asignar');
        $links = array_map('e', $this->appointmentActionLinks($appointment));
        $timeLine = $startsAt.($endsAt ? ' - '.e($endsAt) : '');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#10131a;">
    <div style="max-width:620px;margin:0 auto;padding:28px 18px;">
        <div style="background:#ffffff;border:1px solid #dde3ea;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#10131a;">Tu cita fue creada</h1>
            <p style="margin:0 0 18px;font-size:16px;line-height:1.55;">Hola {$clientName}, tu cita en <strong>{$clinicName}</strong> fue creada para la hora correspondiente.</p>
            <div style="border:1px solid #dde3ea;border-radius:8px;padding:16px;margin:0 0 20px;background:#fbfcfe;">
                <p style="margin:0 0 8px;"><strong>Servicio:</strong> {$serviceName}</p>
                <p style="margin:0 0 8px;"><strong>Fecha y hora:</strong> {$timeLine}</p>
                <p style="margin:0;"><strong>Estilista:</strong> {$stylist}</p>
            </div>
            <div style="display:block;margin:0 0 18px;">
                <a href="{$links['confirm']}" style="display:inline-block;margin:0 8px 10px 0;padding:12px 18px;border-radius:6px;background:#166534;color:#ffffff;text-decoration:none;font-weight:700;">Confirmar</a>
                <a href="{$links['reschedule']}" style="display:inline-block;margin:0 8px 10px 0;padding:12px 18px;border-radius:6px;background:#c0265a;color:#ffffff;text-decoration:none;font-weight:700;">Modificar</a>
                <a href="{$links['cancel']}" style="display:inline-block;margin:0 0 10px 0;padding:12px 18px;border-radius:6px;background:#991b1b;color:#ffffff;text-decoration:none;font-weight:700;">Cancelar cita</a>
            </div>
            <p style="margin:0;color:#647084;font-size:14px;line-height:1.5;">Si los botones no abren, copia el enlace correspondiente desde este correo.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function appointmentActionLinks(Appointment $appointment): array
    {
        $token = $this->reminders->tokenFor($appointment);

        return [
            'confirm' => route('public-appointments.confirm', [$appointment, $token]),
            'reschedule' => route('public-reschedule.show', [$appointment, $token]),
            'cancel' => route('public-appointments.cancel', [$appointment, $token]),
        ];
    }

    private function updatedAppointmentMessage(Appointment $appointment): string
    {
        $clinicName = $appointment->clinic?->name ?? 'Secretaria Virtual';
        $serviceName = $appointment->service?->name ?? $appointment->reason ?? 'tu cita';
        $startsAt = $appointment->starts_at
            ->copy()
            ->timezone($appointment->clinic?->localTimezone() ?: config('app.timezone'))
            ->format('m/d/Y g:i A');

        return "{$clinicName}: tu cita de {$serviceName} fue actualizada para {$startsAt}.";
    }

    private function updatedAppointmentEmailBody(Appointment $appointment): string
    {
        $clinicName = $appointment->clinic?->name ?? 'Secretaria Virtual';
        $clientName = trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? ''));
        $serviceName = $appointment->service?->name ?? $appointment->reason ?? 'Cita';
        $startsAt = $appointment->starts_at
            ->copy()
            ->timezone($appointment->clinic?->localTimezone() ?: config('app.timezone'))
            ->format('m/d/Y g:i A');
        $endsAt = $appointment->ends_at
            ? $appointment->ends_at->copy()->timezone($appointment->clinic?->localTimezone() ?: config('app.timezone'))->format('g:i A')
            : null;
        $stylist = $appointment->stylist?->name ?? 'Por asignar';

        return implode("\n", array_filter([
            'Hola '.($clientName ?: 'cliente').',',
            '',
            "Tu cita en {$clinicName} fue actualizada.",
            '',
            "Servicio: {$serviceName}",
            "Nueva fecha y hora: {$startsAt}".($endsAt ? " - {$endsAt}" : ''),
            "Estilista: {$stylist}",
            '',
            'Si necesitas cambiar o cancelar la cita, responde a este correo o contacta al salon.',
            '',
            'Gracias.',
        ]));
    }

    private function reconfirmedAppointmentEmailBody(Appointment $appointment): string
    {
        $clinicName = $appointment->clinic?->name ?? 'Secretaria Virtual';
        $clientName = trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? ''));
        $serviceName = $appointment->service?->name ?? $appointment->reason ?? 'Cita';
        $timezone = $appointment->clinic?->localTimezone() ?: config('app.timezone');
        $startsAt = $appointment->starts_at->copy()->timezone($timezone)->format('m/d/Y g:i A');
        $endsAt = $appointment->ends_at
            ? $appointment->ends_at->copy()->timezone($timezone)->format('g:i A')
            : null;
        $stylist = $appointment->stylist?->name ?? 'Por asignar';

        return implode("\n", array_filter([
            'Hola '.($clientName ?: 'cliente').',',
            '',
            "Acabas de reagendar tu cita en {$clinicName}.",
            '',
            "Servicio: {$serviceName}",
            "Fecha y hora: {$startsAt}".($endsAt ? " - {$endsAt}" : ''),
            "Estilista: {$stylist}",
            '',
            'Tu cita quedo confirmada nuevamente.',
            '',
            'Gracias.',
        ]));
    }

    private function enabled(Appointment $appointment, string $key): bool
    {
        return $appointment->clinic?->notificationEnabled($key) ?? true;
    }

    private function recordNotification(
        Appointment $appointment,
        string $channel,
        string $event,
        ?string $recipient,
        string $status,
        string $body,
        ?string $providerMessageId = null,
        ?string $error = null,
    ): void {
        if (! $recipient) {
            return;
        }

        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => $channel,
            'event' => $event,
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'body' => $body,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
