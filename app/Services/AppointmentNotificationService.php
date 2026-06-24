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
                Mail::send([], [], function ($message) use ($emailBody, $emailHtml, $appointment, $client): void {
                    $message
                        ->to($client->email)
                        ->subject('Tu cita fue creada - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));

                    $message->getSymfonyMessage()
                        ->html($emailHtml)
                        ->text($emailBody);
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
            $emailHtml = $this->updatedAppointmentEmailHtml($appointment);

            try {
                Mail::send([], [], function ($message) use ($emailBody, $emailHtml, $appointment, $client): void {
                    $message
                        ->to($client->email)
                        ->subject('Cambio en tu cita - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));

                    $message->getSymfonyMessage()
                        ->html($emailHtml)
                        ->text($emailBody);
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
        $emailHtml = $this->reconfirmedAppointmentEmailHtml($appointment);

        try {
            Mail::send([], [], function ($message) use ($emailBody, $emailHtml, $appointment, $client): void {
                $message
                    ->to($client->email)
                    ->subject('Tu cita fue reagendada - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));

                $message->getSymfonyMessage()
                    ->html($emailHtml)
                    ->text($emailBody);
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
        $links = array_map('e', $this->appointmentActionLinks($appointment));

        return $this->appointmentEmailHtml(
            $appointment,
            'Tu cita fue creada',
            'Hemos reservado tu cita correctamente. Revisa los detalles y confirma, modifica o cancela si necesitas hacer algun cambio.',
            '<a href="'.$links['confirm'].'" style="display:inline-block;margin:0 8px 10px 0;padding:12px 18px;border-radius:6px;background:#166534;color:#ffffff;text-decoration:none;font-weight:800;">Confirmar</a>
                <a href="'.$links['reschedule'].'" style="display:inline-block;margin:0 8px 10px 0;padding:12px 18px;border-radius:6px;background:#c0265a;color:#ffffff;text-decoration:none;font-weight:800;">Modificar</a>
                <a href="'.$links['cancel'].'" style="display:inline-block;margin:0 0 10px 0;padding:12px 18px;border-radius:6px;background:#991b1b;color:#ffffff;text-decoration:none;font-weight:800;">Cancelar cita</a>',
        );
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

    private function updatedAppointmentEmailHtml(Appointment $appointment): string
    {
        return $this->appointmentEmailHtml(
            $appointment,
            'Tu cita fue actualizada',
            'Te avisamos que los detalles de tu cita cambiaron. Debajo tienes la informacion actualizada para que la tengas a mano.',
        );
    }

    private function reconfirmedAppointmentEmailHtml(Appointment $appointment): string
    {
        return $this->appointmentEmailHtml(
            $appointment,
            'Tu cita fue reagendada',
            'Tu nueva fecha quedo confirmada correctamente. Te esperamos en el horario actualizado.',
        );
    }

    private function appointmentEmailHtml(Appointment $appointment, string $title, string $intro, string $actions = ''): string
    {
        $clinicName = e($appointment->clinic?->name ?? 'Secretaria Virtual');
        $clientName = e(trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')) ?: 'cliente');
        $serviceName = e($appointment->service?->name ?? $appointment->reason ?? 'Cita');
        $timezone = $appointment->clinic?->localTimezone() ?: config('app.timezone');
        $startsAt = e($appointment->starts_at->copy()->timezone($timezone)->format('m/d/Y g:i A'));
        $endsAt = $appointment->ends_at?->copy()->timezone($timezone)->format('g:i A');
        $timeLine = $startsAt.($endsAt ? ' - '.e($endsAt) : '');
        $stylist = e($appointment->stylist?->name ?? 'Por asignar');
        $logoUrl = e(url('/logo-login-v2.png'));
        $actionsBlock = $actions !== ''
            ? '<div style="display:block;margin:0 0 18px;">'.$actions.'</div>'
            : '';

        return '<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$this->escape($title).'</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e8ecf2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#111827;padding:26px 30px;color:#ffffff;">
                            <img src="'.$logoUrl.'" width="190" alt="Secretary365" style="display:block;width:190px;max-width:100%;height:auto;border:0;margin:0 0 18px;">
                            <h1 style="margin:0;font-size:26px;line-height:1.25;font-weight:900;">'.$this->escape($title).'</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">Hola <strong>'.$clientName.'</strong>,</p>
                            <p style="margin:0 0 22px;font-size:16px;line-height:1.55;">'.$this->escape($intro).'</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:8px;margin:0 0 22px;">
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Salon</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;">'.$clinicName.'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Servicio</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;">'.$serviceName.'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Fecha y hora</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;color:#166534;">'.$timeLine.'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Estilista</td>
                                    <td align="right" style="padding:16px 18px;font-size:15px;font-weight:900;">'.$stylist.'</td>
                                </tr>
                            </table>
                            '.$actionsBlock.'
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;color:#475569;font-size:14px;line-height:1.55;">
                                Si tienes alguna pregunta, responde a este correo o contacta directamente al salon.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 30px;background:#fbfcfe;border-top:1px solid #edf0f5;color:#6b7280;font-size:13px;line-height:1.5;">
                            Gestionado por <a href="https://secretary365.com" style="color:#374151;font-weight:800;text-decoration:none;">Secretary365.com</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
