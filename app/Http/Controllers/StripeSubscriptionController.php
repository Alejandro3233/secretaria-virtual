<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use App\Services\BillingInvoiceEmailService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class StripeSubscriptionController extends Controller
{
    public function checkout(Request $request, SubscriptionPlan $plan, StripeCheckoutService $stripe): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $plan->is_active, 404);

        try {
            return redirect()->away($stripe->createCheckoutSession($clinic, $plan));
        } catch (RuntimeException $exception) {
            return back()->with('stripe_error', $exception->getMessage());
        }
    }

    public function success(Request $request, StripeCheckoutService $stripe): View
    {
        $clinic = $request->user()->primaryClinic();
        $status = 'Tu suscripcion fue procesada correctamente.';
        $error = null;

        if ($clinic && $request->query('session_id')) {
            try {
                $stripe->confirmCheckoutSession($clinic, (string) $request->query('session_id'));
                $clinic->refresh();
                $status = 'Tu suscripcion fue confirmada y el plan del salon quedo actualizado.';
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('stripe.success', [
            'clinic' => $clinic,
            'status' => $status,
            'error' => $error,
        ]);
    }

    public function cancel(): View
    {
        return view('stripe.cancel');
    }

    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();

        if (! $this->validSignature($payload, (string) $request->header('Stripe-Signature'))) {
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Invalid payload', 400);
        }

        match ($event['type'] ?? '') {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event['data']['object'] ?? []),
            'customer.subscription.updated', 'customer.subscription.deleted' => $this->handleSubscriptionChanged($event['data']['object'] ?? []),
            'invoice.paid', 'invoice.payment_succeeded' => $this->handleInvoicePaid($event['data']['object'] ?? []),
            default => null,
        };

        return response('OK');
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $clinicId = (int) ($session['metadata']['clinic_id'] ?? $session['client_reference_id'] ?? 0);
        $planId = (int) ($session['metadata']['plan_id'] ?? 0);

        if (! $clinicId || ! $planId) {
            return;
        }

        Clinic::query()
            ->whereKey($clinicId)
            ->update([
                'subscription_plan_id' => $planId,
                'stripe_customer_id' => $session['customer'] ?? null,
                'stripe_subscription_id' => $session['subscription'] ?? null,
                'subscription_status' => 'active',
                'updated_at' => now(),
            ]);
    }

    private function handleSubscriptionChanged(array $subscription): void
    {
        $stripeSubscriptionId = $subscription['id'] ?? null;

        if (! $stripeSubscriptionId) {
            return;
        }

        $status = match ($subscription['status'] ?? null) {
            'active', 'trialing' => 'active',
            'past_due' => 'past_due',
            'canceled', 'unpaid', 'incomplete_expired' => 'cancelled',
            default => $subscription['status'] ?? 'pending',
        };

        DB::table('clinics')
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->update([
                'subscription_status' => $status,
                'subscription_renews_at' => isset($subscription['current_period_end'])
                    ? now()->setTimestamp((int) $subscription['current_period_end'])
                    : null,
                'updated_at' => now(),
            ]);
    }

    private function handleInvoicePaid(array $invoice): void
    {
        app(BillingInvoiceEmailService::class)->sendForPaidInvoice($invoice);
    }

    private function validSignature(string $payload, string $signatureHeader): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return true;
        }

        parse_str(str_replace(',', '&', $signatureHeader), $signatureParts);

        if (empty($signatureParts['t']) || empty($signatureParts['v1'])) {
            return false;
        }

        $signedPayload = $signatureParts['t'].'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, (string) $signatureParts['v1']);
    }
}
