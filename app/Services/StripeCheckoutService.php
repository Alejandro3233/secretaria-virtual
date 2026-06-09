<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckoutService
{
    public function createCheckoutSession(Clinic $clinic, SubscriptionPlan $plan): string
    {
        $secretKey = (string) config('services.stripe.secret_key');

        if ($secretKey === '') {
            throw new RuntimeException('Configura STRIPE_SECRET_KEY en el archivo .env.');
        }

        $priceId = $plan->stripe_price_id ?: $this->priceIdFromConfig($plan);

        if (! $priceId) {
            throw new RuntimeException('Este plan no tiene stripe_price_id configurado.');
        }

        $customerId = $clinic->stripe_customer_id ?: $this->createCustomer($clinic);

        if (! $clinic->stripe_customer_id) {
            $clinic->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        $response = Http::asForm()
            ->withToken($secretKey)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'customer' => $customerId,
                'client_reference_id' => (string) $clinic->id,
                'success_url' => url('/stripe/exito').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/stripe/cancelado'),
                'line_items[0][price]' => $priceId,
                'line_items[0][quantity]' => 1,
                'metadata[clinic_id]' => (string) $clinic->id,
                'metadata[plan_id]' => (string) $plan->id,
                'subscription_data[metadata][clinic_id]' => (string) $clinic->id,
                'subscription_data[metadata][plan_id]' => (string) $plan->id,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo crear la sesion de pago.');
        }

        $url = $response->json('url');

        if (! $url) {
            throw new RuntimeException('Stripe no devolvio la URL de checkout.');
        }

        return $url;
    }

    public function confirmCheckoutSession(Clinic $clinic, string $sessionId): void
    {
        $response = Http::withToken((string) config('services.stripe.secret_key'))
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo confirmar la sesion de pago.');
        }

        $session = $response->json();
        $clinicId = (int) ($session['metadata']['clinic_id'] ?? $session['client_reference_id'] ?? 0);
        $planId = (int) ($session['metadata']['plan_id'] ?? 0);

        if ($clinicId !== $clinic->id) {
            throw new RuntimeException('La sesion de Stripe no pertenece a este salon.');
        }

        $clinic->forceFill([
            'subscription_plan_id' => $planId ?: $clinic->subscription_plan_id,
            'stripe_customer_id' => $session['customer'] ?? $clinic->stripe_customer_id,
            'stripe_subscription_id' => $session['subscription'] ?? $clinic->stripe_subscription_id,
            'subscription_status' => ($session['payment_status'] ?? null) === 'paid' ? 'active' : $clinic->subscription_status,
        ])->save();
    }

    private function createCustomer(Clinic $clinic): string
    {
        $response = Http::asForm()
            ->withToken((string) config('services.stripe.secret_key'))
            ->post('https://api.stripe.com/v1/customers', [
                'name' => $clinic->name,
                'email' => $clinic->email,
                'phone' => $clinic->phone,
                'metadata[clinic_id]' => (string) $clinic->id,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo crear el cliente.');
        }

        return (string) $response->json('id');
    }

    private function priceIdFromConfig(SubscriptionPlan $plan): ?string
    {
        return match ($plan->slug) {
            'basico' => env('STRIPE_PRICE_BASICO') ?: null,
            'profesional' => env('STRIPE_PRICE_PROFESIONAL') ?: null,
            'clinica-plus', 'salon-plus' => env('STRIPE_PRICE_SALON_PLUS') ?: null,
            default => null,
        };
    }
}
