<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppointmentNotificationService
{
    public function __construct(private readonly TwilioSmsService $sms)
    {
    }

    public function appointmentCreated(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'client', 'service', 'stylist']);
        $client = $appointment->client;

        if (! $client) {
            return;
        }

        if (in_array($client->notification_preference, ['sms', 'both'], true)) {
            $smsBody = $this->newAppointmentMessage($appointment);

            try {
                $providerMessageId = $this->sms->send($client->phone, $smsBody);
                $this->recordNotification($appointment, 'sms', $client->phone, 'sent', $smsBody, $providerMessageId);
            } catch (\Throwable $exception) {
                $this->recordNotification($appointment, 'sms', $client->phone, 'failed', $smsBody, null, $exception->getMessage());
                Log::warning('No se pudo enviar SMS de nueva cita.', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($client->email && in_array($client->notification_preference, ['email', 'both'], true)) {
            $emailBody = $this->newAppointmentEmailBody($appointment);

            try {
                Mail::raw($emailBody, function ($message) use ($appointment, $client) {
                    $message
                        ->to($client->email)
                        ->subject('Confirmacion de cita - '.($appointment->clinic?->name ?? 'Secretaria Virtual'));
                });
                $this->recordNotification($appointment, 'email', $client->email, 'sent', $emailBody);
            } catch (\Throwable $exception) {
                $this->recordNotification($appointment, 'email', $client->email, 'failed', $emailBody, null, $exception->getMessage());
                Log::warning('No se pudo enviar email de nueva cita.', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);
            }
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
        $startsAt = $appointment->starts_at->format('m/d/Y g:i A');
        $endsAt = $appointment->ends_at?->format('g:i A');
        $stylist = $appointment->stylist?->name ?? 'Por asignar';

        return implode("\n", array_filter([
            'Hola '.($clientName ?: 'cliente').',',
            '',
            "Tu cita en {$clinicName} quedo confirmada.",
            '',
            "Servicio: {$serviceName}",
            "Fecha y hora: {$startsAt}".($endsAt ? " - {$endsAt}" : ''),
            "Estilista: {$stylist}",
            '',
            'Si necesitas cambiar o cancelar la cita, responde a este correo o contacta al salon.',
            '',
            'Gracias.',
        ]));
    }

    private function recordNotification(
        Appointment $appointment,
        string $channel,
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
            'event' => 'appointment_created',
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
