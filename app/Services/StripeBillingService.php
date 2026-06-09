<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeBillingService
{
    public function invoicesForClinic(Clinic $clinic): Collection
    {
        if (! $clinic->stripe_customer_id) {
            return collect();
        }

        $response = Http::withToken($this->secretKey())
            ->get('https://api.stripe.com/v1/invoices', [
                'customer' => $clinic->stripe_customer_id,
                'limit' => 20,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo cargar las facturas.');
        }

        return collect($response->json('data', []))
            ->map(fn (array $invoice): array => $this->formatInvoice($invoice));
    }

    public function subscriptionSummary(Clinic $clinic): array
    {
        $fallback = [
            'purchased_at' => null,
            'renews_at' => $clinic->subscription_renews_at,
            'status' => $clinic->subscription_status,
        ];

        if (! $clinic->stripe_subscription_id) {
            return $fallback;
        }

        $response = Http::withToken($this->secretKey())
            ->get("https://api.stripe.com/v1/subscriptions/{$clinic->stripe_subscription_id}");

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo cargar la suscripcion.');
        }

        $subscription = $response->json();
        $purchasedAt = isset($subscription['created'])
            ? Carbon::createFromTimestamp((int) $subscription['created'])
            : null;
        $renewsAt = isset($subscription['current_period_end'])
            ? Carbon::createFromTimestamp((int) $subscription['current_period_end'])
            : $clinic->subscription_renews_at;

        return [
            'purchased_at' => $purchasedAt,
            'renews_at' => $renewsAt,
            'status' => $subscription['status'] ?? $clinic->subscription_status,
        ];
    }

    public function invoicePdfUrl(Clinic $clinic, string $invoiceId): string
    {
        if (! $clinic->stripe_customer_id) {
            throw new RuntimeException('Este salon no tiene cliente Stripe asociado.');
        }

        $response = Http::withToken($this->secretKey())
            ->get("https://api.stripe.com/v1/invoices/{$invoiceId}");

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo cargar esta factura.');
        }

        $invoice = $response->json();

        if (($invoice['customer'] ?? null) !== $clinic->stripe_customer_id) {
            throw new RuntimeException('Esta factura no pertenece a este salon.');
        }

        $pdfUrl = $invoice['invoice_pdf'] ?? $invoice['hosted_invoice_url'] ?? null;

        if (! $pdfUrl) {
            throw new RuntimeException('Stripe todavia no genero el PDF de esta factura.');
        }

        return $pdfUrl;
    }

    private function formatInvoice(array $invoice): array
    {
        $currency = strtoupper((string) ($invoice['currency'] ?? 'usd'));
        $amountPaid = (int) ($invoice['amount_paid'] ?? 0);
        $amountDue = (int) ($invoice['amount_due'] ?? 0);
        $createdAt = isset($invoice['created']) ? Carbon::createFromTimestamp((int) $invoice['created']) : null;

        return [
            'id' => $invoice['id'] ?? '',
            'number' => $invoice['number'] ?? $invoice['id'] ?? 'Factura',
            'status' => $invoice['status'] ?? 'pending',
            'created_at' => $createdAt,
            'period' => $this->periodLabel($invoice),
            'amount' => $this->money($amountPaid > 0 ? $amountPaid : $amountDue, $currency),
            'currency' => $currency,
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
        ];
    }

    private function periodLabel(array $invoice): string
    {
        $line = $invoice['lines']['data'][0] ?? null;
        $start = $line['period']['start'] ?? null;
        $end = $line['period']['end'] ?? null;

        if (! $start || ! $end) {
            return 'Periodo no disponible';
        }

        return Carbon::createFromTimestamp((int) $start)->format('d/m/Y')
            .' - '
            .Carbon::createFromTimestamp((int) $end)->format('d/m/Y');
    }

    private function money(int $amount, string $currency): string
    {
        return '$'.number_format($amount / 100, 2).' '.$currency;
    }

    private function secretKey(): string
    {
        $secretKey = (string) config('services.stripe.secret_key');

        if ($secretKey === '') {
            throw new RuntimeException('Configura STRIPE_SECRET_KEY en el archivo .env.');
        }

        return $secretKey;
    }
}
