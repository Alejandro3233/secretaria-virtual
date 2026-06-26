<?php

namespace App\Services;

use App\Models\AppointmentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class AppointmentPaymentReceiptEmailService
{
    public function send(AppointmentPayment $payment): void
    {
        $payment->loadMissing(['appointment.service', 'appointment.client', 'clinic', 'client']);

        if (! $payment->clinic || ! $payment->appointment) {
            return;
        }

        if ($this->alreadySent($payment)) {
            return;
        }

        $recipient = trim((string) ($payment->client?->email ?? $payment->appointment?->client?->email ?? ''));
        $body = $this->bodyFor($payment);
        $html = $this->emailHtmlFor($payment);
        $attachment = $this->invoiceHtmlFor($payment);
        $filename = $this->filenameFor($payment);

        try {
            if ($recipient === '') {
                throw new RuntimeException('El cliente no tiene correo electronico.');
            }

            Mail::send([], [], function ($message) use ($payment, $recipient, $body, $html, $attachment, $filename): void {
                $message
                    ->to($recipient)
                    ->subject('Confirmacion de pago - '.$this->clinicName($payment))
                    ->attachData($attachment, $filename, ['mime' => 'text/html']);

                $message->getSymfonyMessage()
                    ->html($html)
                    ->text($body);
            });

            $this->record($payment, $recipient, 'sent', $body);
        } catch (\Throwable $exception) {
            $this->record($payment, $recipient ?: 'unknown', 'failed', $body, $exception->getMessage());

            Log::warning('No se pudo enviar comprobante de pago de cita.', [
                'payment_id' => $payment->id,
                'appointment_id' => $payment->appointment_id,
                'clinic_id' => $payment->clinic_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function alreadySent(AppointmentPayment $payment): bool
    {
        return DB::table('notifications')
            ->where('clinic_id', $payment->clinic_id)
            ->where('appointment_id', $payment->appointment_id)
            ->where('channel', 'email')
            ->where('event', 'appointment_payment_receipt')
            ->where('provider_message_id', $this->providerId($payment))
            ->where('status', 'sent')
            ->exists();
    }

    private function bodyFor(AppointmentPayment $payment): string
    {
        $lines = [
            'Hola '.$this->clientName($payment).',',
            '',
            'Te confirmamos que recibimos tu pago correctamente.',
            '',
            'Salon: '.$this->clinicName($payment),
            'Servicio: '.$this->serviceName($payment),
            'Importe pagado: '.$this->money($payment),
            'Metodo de pago: '.$this->methodLabel($payment),
            'Fecha de pago: '.$this->paidAtLabel($payment),
            '',
            'Adjuntamos el comprobante de pago para tus registros.',
            '',
            'Gracias por tu visita.',
        ];

        return implode("\n", $lines);
    }

    private function emailHtmlFor(AppointmentPayment $payment): string
    {
        return '<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmacion de pago</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e8ecf2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#111827;padding:26px 30px;color:#ffffff;">
                            <div style="font-size:13px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#f9c7d8;">'.$this->e($this->clinicName($payment)).'</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:900;">Pago recibido</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">Hola <strong>'.$this->e($this->clientName($payment)).'</strong>,</p>
                            <p style="margin:0 0 22px;font-size:16px;line-height:1.55;">Te confirmamos que recibimos tu pago correctamente. Adjuntamos el comprobante para tus registros.</p>
                            '.$this->summaryTable($payment).'
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;color:#475569;font-size:14px;line-height:1.55;">
                                Si tienes alguna pregunta sobre este pago, responde a este correo y te ayudaremos.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 30px;background:#fbfcfe;border-top:1px solid #edf0f5;color:#6b7280;font-size:13px;line-height:1.5;">
                            Gracias por tu visita.<br>
                            Este correo fue generado automaticamente despues de confirmarse el pago.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    private function invoiceHtmlFor(AppointmentPayment $payment): string
    {
        return '<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Comprobante de pago</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#111827;margin:32px;">
    <h1 style="margin:0 0 8px;">Comprobante de pago</h1>
    <p style="margin:0 0 24px;color:#6b7280;">'.$this->e($this->clinicName($payment)).'</p>
    '.$this->summaryTable($payment).'
    <p style="margin-top:24px;color:#6b7280;font-size:13px;">Documento generado automaticamente por Secretary365.</p>
</body>
</html>';
    }

    private function summaryTable(AppointmentPayment $payment): string
    {
        $rows = [
            'Comprobante' => 'Pago #'.$payment->id,
            'Cliente' => $this->clientName($payment),
            'Servicio' => $this->serviceName($payment),
            'Importe pagado' => $this->money($payment),
            'Metodo de pago' => $this->methodLabel($payment),
            'Fecha de pago' => $this->paidAtLabel($payment),
        ];

        $html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:8px;margin:0 0 22px;border-collapse:separate;overflow:hidden;">';

        foreach ($rows as $label => $value) {
            $html .= '<tr>
                <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:12px;font-weight:800;text-transform:uppercase;">'.$this->e($label).'</td>
                <td align="right" style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;">'.$this->e($value).'</td>
            </tr>';
        }

        return $html.'</table>';
    }

    private function record(AppointmentPayment $payment, string $recipient, string $status, string $body, ?string $error = null): void
    {
        DB::table('notifications')->insert([
            'clinic_id' => $payment->clinic_id,
            'client_id' => $payment->client_id,
            'appointment_id' => $payment->appointment_id,
            'channel' => 'email',
            'event' => 'appointment_payment_receipt',
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => $this->providerId($payment),
            'body' => $body,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function providerId(AppointmentPayment $payment): string
    {
        return 'appointment_payment:'.$payment->id;
    }

    private function filenameFor(AppointmentPayment $payment): string
    {
        return 'comprobante-pago-'.$payment->id.'.html';
    }

    private function clientName(AppointmentPayment $payment): string
    {
        $client = $payment->client ?? $payment->appointment?->client;
        $name = trim((string) (($client?->first_name ?? '').' '.($client?->last_name ?? '')));

        return $name !== '' ? $name : 'Cliente';
    }

    private function clinicName(AppointmentPayment $payment): string
    {
        return $payment->clinic?->name ?: 'el salon';
    }

    private function serviceName(AppointmentPayment $payment): string
    {
        return $payment->appointment?->service?->name
            ?: ($payment->appointment?->reason ?: 'Servicio');
    }

    private function methodLabel(AppointmentPayment $payment): string
    {
        return match ($payment->method) {
            'cash' => 'Efectivo',
            'stripe' => 'Tarjeta',
            'other' => 'Otro metodo',
            default => ucfirst((string) $payment->method),
        };
    }

    private function paidAtLabel(AppointmentPayment $payment): string
    {
        $timezone = $payment->clinic?->timezone ?: config('app.timezone', 'UTC');

        return ($payment->paid_at ?: now())->copy()->timezone($timezone)->format('d/m/Y H:i');
    }

    private function money(AppointmentPayment $payment): string
    {
        return '$'.number_format($payment->amount_cents / 100, 2).' '.strtoupper($payment->currency ?: 'USD');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
