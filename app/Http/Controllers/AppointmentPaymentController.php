<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Services\AppointmentService;
use App\Services\AppointmentPaymentReceiptEmailService;
use App\Services\TwilioSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AppointmentPaymentController extends Controller
{
    public function store(Request $request, Appointment $appointment, AppointmentService $appointments, AppointmentPaymentReceiptEmailService $receipts, TwilioSmsService $sms): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $appointment->clinic_id === $clinic->id, 404);
        $appointment->loadMissing(['client', 'service']);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'method' => ['required', 'string', 'in:cash,stripe,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'complete_appointment' => ['nullable', 'boolean'],
        ]);

        $amountCents = (int) round(((float) $data['amount']) * 100);
        $method = (string) $data['method'];

        if ($method === 'stripe') {
            try {
                $payment = $this->createStripePayment($request, $appointment, $amountCents, (string) ($data['notes'] ?? ''));
                $sent = $this->sendStripeLink($payment, $sms);
                $message = $sent
                    ? 'Enlace Stripe creado y enviado al cliente. La cita queda pendiente de pago.'
                    : 'Enlace Stripe creado. Copialo y envialo al cliente: '.$payment->checkout_url;

                return back()->with('appointment_status', $message);
            } catch (\Throwable $exception) {
                return back()->with('appointment_error', $exception->getMessage());
            }
        }

        $payment = AppointmentPayment::query()->create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'user_id' => $request->user()->id,
            'amount_cents' => $amountCents,
            'currency' => 'usd',
            'method' => $method,
            'status' => 'paid',
            'notes' => $data['notes'] ?? null,
            'paid_at' => now(),
        ]);

        if ($request->boolean('complete_appointment', true) && ! in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            $appointments->update($appointment, ['status' => 'completed']);
        }

        $receipts->send($payment);

        $methodLabel = $payment->method === 'cash' ? 'efectivo' : 'otro metodo';

        return back()->with('appointment_status', 'Pago registrado en '.$methodLabel.'. Cita cerrada.');
    }

    private function createStripePayment(Request $request, Appointment $appointment, int $amountCents, string $notes): AppointmentPayment
    {
        $secretKey = (string) config('services.stripe.secret_key');

        if ($secretKey === '') {
            throw new RuntimeException('Falta configurar STRIPE_SECRET_KEY para crear enlaces de pago.');
        }

        $payment = AppointmentPayment::query()->create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'user_id' => $request->user()->id,
            'amount_cents' => $amountCents,
            'currency' => 'usd',
            'method' => 'stripe',
            'status' => 'pending',
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $clientName = trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')) ?: 'Cliente';
        $serviceName = $appointment->service?->name ?: ($appointment->reason ?: 'Servicio');

        $response = Http::asForm()
            ->withToken($secretKey)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => url('/pagos/exito'),
                'cancel_url' => url('/pagos/cancelado'),
                'client_reference_id' => (string) $payment->id,
                'customer_email' => $appointment->client?->email ?: null,
                'metadata[type]' => 'appointment_payment',
                'metadata[payment_id]' => (string) $payment->id,
                'metadata[clinic_id]' => (string) $appointment->clinic_id,
                'metadata[appointment_id]' => (string) $appointment->id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'usd',
                'line_items[0][price_data][unit_amount]' => $amountCents,
                'line_items[0][price_data][product_data][name]' => $serviceName.' - '.$clientName,
            ]);

        if ($response->failed()) {
            $payment->update(['status' => 'failed']);

            throw new RuntimeException($response->json('error.message') ?: 'Stripe no pudo crear el enlace de pago.');
        }

        $payment->update([
            'stripe_checkout_session_id' => (string) $response->json('id'),
            'checkout_url' => (string) $response->json('url'),
        ]);

        return $payment->refresh();
    }

    private function sendStripeLink(AppointmentPayment $payment, TwilioSmsService $sms): bool
    {
        $payment->loadMissing(['appointment.client', 'clinic']);
        $phone = $payment->appointment?->client?->phone;
        $url = (string) $payment->checkout_url;

        if (! $phone || $url === '') {
            return false;
        }

        $clinicName = $payment->clinic?->name ?: 'el salon';
        $body = $clinicName.': puedes pagar tu servicio aqui: '.$url;

        try {
            return (bool) $sms->send($phone, $body);
        } catch (\Throwable) {
            return false;
        }
    }
}
