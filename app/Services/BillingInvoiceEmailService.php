<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class BillingInvoiceEmailService
{
    public function sendForPaidInvoice(array $invoice): void
    {
        $invoiceId = (string) ($invoice['id'] ?? '');

        if ($invoiceId === '') {
            return;
        }

        $clinic = $this->clinicForInvoice($invoice);

        if (! $clinic) {
            Log::warning('No se encontro salon para factura pagada de Stripe.', [
                'invoice_id' => $invoiceId,
                'customer' => $invoice['customer'] ?? null,
            ]);

            return;
        }

        if ($this->alreadySent($clinic, $invoiceId)) {
            return;
        }

        $recipient = $this->recipientFor($clinic, $invoice);
        $body = $this->bodyFor($clinic, $invoice);
        $html = $this->htmlFor($clinic, $invoice);

        try {
            if ($recipient === '') {
                throw new RuntimeException('La factura no tiene correo de destinatario.');
            }

            $pdf = $this->downloadPdf($invoice);
            $filename = $this->filenameFor($invoice);

            Mail::send([], [], function ($message) use ($body, $html, $invoice, $recipient, $pdf, $filename): void {
                $message
                    ->to($recipient)
                    ->subject('Factura de cobro '.$this->invoiceNumber($invoice))
                    ->attachData($pdf, $filename, ['mime' => 'application/pdf']);

                $message->getSymfonyMessage()
                    ->html($html)
                    ->text($body);
            });

            $this->record($clinic, $invoiceId, $recipient, 'sent', $body);
        } catch (\Throwable $exception) {
            $this->record($clinic, $invoiceId, $recipient ?: 'unknown', 'failed', $body, $exception->getMessage());

            Log::warning('No se pudo enviar email de factura pagada.', [
                'clinic_id' => $clinic->id,
                'invoice_id' => $invoiceId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function clinicForInvoice(array $invoice): ?Clinic
    {
        $customerId = (string) ($invoice['customer'] ?? '');

        if ($customerId !== '') {
            $clinic = Clinic::query()
                ->where('stripe_customer_id', $customerId)
                ->first();

            if ($clinic) {
                return $clinic;
            }
        }

        $subscriptionId = (string) (
            $invoice['subscription']
            ?? $invoice['parent']['subscription_details']['subscription']
            ?? ''
        );

        if ($subscriptionId === '') {
            return null;
        }

        return Clinic::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();
    }

    private function alreadySent(Clinic $clinic, string $invoiceId): bool
    {
        return DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('channel', 'email')
            ->where('event', 'billing_invoice_paid')
            ->where('provider_message_id', $invoiceId)
            ->where('status', 'sent')
            ->exists();
    }

    private function recipientFor(Clinic $clinic, array $invoice): string
    {
        return trim((string) ($invoice['customer_email'] ?? $clinic->email ?? ''));
    }

    private function downloadPdf(array $invoice): string
    {
        $pdfUrl = (string) ($invoice['invoice_pdf'] ?? '');

        if ($pdfUrl === '') {
            throw new RuntimeException('Stripe todavia no genero el PDF de esta factura.');
        }

        $response = Http::get($pdfUrl);

        if ($response->failed()) {
            throw new RuntimeException('No se pudo descargar el PDF de la factura.');
        }

        return $response->body();
    }

    private function bodyFor(Clinic $clinic, array $invoice): string
    {
        $paidAt = $this->paidAt($invoice);
        $invoiceUrl = (string) ($invoice['hosted_invoice_url'] ?? '');
        $lines = [
            'Hola '.$clinic->name.',',
            '',
            'Te informamos que se realizo un cobro correctamente en Secretaria Virtual.',
            '',
            'Factura: '.$this->invoiceNumber($invoice),
            'Importe cobrado: '.$this->money((int) ($invoice['amount_paid'] ?? $invoice['total'] ?? 0), (string) ($invoice['currency'] ?? 'usd')),
            'Fecha del cobro: '.($paidAt ? $paidAt->format('d/m/Y H:i') : now()->format('d/m/Y H:i')),
            '',
            'Adjuntamos la factura en PDF para tus registros.',
            '',
        ];

        if ($invoiceUrl !== '') {
            $lines[] = 'Tambien puedes verla online aqui: '.$invoiceUrl;
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            'Si tienes alguna pregunta sobre este cobro, responde a este correo y te ayudaremos.',
            '',
            'Gracias por confiar en Secretaria Virtual.',
        ]);

        return implode("\n", $lines);
    }

    private function htmlFor(Clinic $clinic, array $invoice): string
    {
        $paidAt = $this->paidAt($invoice);
        $invoiceNumber = $this->invoiceNumber($invoice);
        $amount = $this->money((int) ($invoice['amount_paid'] ?? $invoice['total'] ?? 0), (string) ($invoice['currency'] ?? 'usd'));
        $paidAtLabel = $paidAt ? $paidAt->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
        $invoiceUrl = (string) ($invoice['hosted_invoice_url'] ?? '');
        $appName = config('app.name') === 'Laravel' ? 'Secretaria Virtual' : (string) config('app.name');

        $button = $invoiceUrl !== ''
            ? '<tr><td style="padding:8px 0 24px;"><a href="'.$this->e($invoiceUrl).'" style="display:inline-block;background:#c0265a;color:#ffffff;text-decoration:none;font-weight:800;border-radius:6px;padding:13px 18px;">Ver factura online</a></td></tr>'
            : '';

        return '<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura de cobro</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e8ecf2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#111827;padding:26px 30px;color:#ffffff;">
                            <div style="font-size:13px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#f9c7d8;">'.$this->e($appName).'</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:900;">Cobro realizado correctamente</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">Hola <strong>'.$this->e($clinic->name).'</strong>,</p>
                            <p style="margin:0 0 22px;font-size:16px;line-height:1.55;">Te confirmamos que el cobro de tu suscripcion fue procesado correctamente. Adjuntamos la factura en PDF para tus registros.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:8px;margin:0 0 22px;">
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Factura</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;">'.$this->e($invoiceNumber).'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Importe cobrado</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:18px;font-weight:900;color:#166534;">'.$this->e($amount).'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Fecha del cobro</td>
                                    <td align="right" style="padding:16px 18px;font-size:15px;font-weight:800;">'.$this->e($paidAtLabel).'</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                '.$button.'
                                <tr>
                                    <td style="padding:0 0 8px;">
                                        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;color:#475569;font-size:14px;line-height:1.55;">
                                            El PDF oficial de la factura esta adjunto a este correo. Si tienes alguna pregunta sobre este cobro, responde a este mensaje y te ayudaremos.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 30px;background:#fbfcfe;border-top:1px solid #edf0f5;color:#6b7280;font-size:13px;line-height:1.5;">
                            Gracias por confiar en <strong style="color:#374151;">'.$this->e($appName).'</strong>.<br>
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

    private function record(Clinic $clinic, string $invoiceId, string $recipient, string $status, string $body, ?string $error = null): void
    {
        DB::table('notifications')->insert([
            'clinic_id' => $clinic->id,
            'client_id' => null,
            'appointment_id' => null,
            'channel' => 'email',
            'event' => 'billing_invoice_paid',
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => $invoiceId,
            'body' => $body,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function invoiceNumber(array $invoice): string
    {
        return (string) ($invoice['number'] ?? $invoice['id'] ?? 'Factura');
    }

    private function filenameFor(array $invoice): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->invoiceNumber($invoice));

        return 'factura-'.$number.'.pdf';
    }

    private function paidAt(array $invoice): ?Carbon
    {
        $timestamp = $invoice['status_transitions']['paid_at'] ?? $invoice['created'] ?? null;

        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    private function money(int $amount, string $currency): string
    {
        return '$'.number_format($amount / 100, 2).' '.strtoupper($currency ?: 'USD');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
