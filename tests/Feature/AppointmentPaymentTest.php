<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_close_appointment_with_cash_payment(): void
    {
        [$user, $appointment] = $this->appointmentFixture();

        $this->actingAs($user)->post(route('appointments.payments.store', $appointment), [
            'amount' => '45.00',
            'method' => 'cash',
            'notes' => 'Pago en caja',
            'complete_appointment' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointment_payments', [
            'appointment_id' => $appointment->id,
            'amount_cents' => 4500,
            'method' => 'cash',
            'status' => 'paid',
            'notes' => 'Pago en caja',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'event' => 'appointment_payment_receipt',
            'recipient' => 'rafael@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_user_can_create_stripe_payment_link_and_webhook_marks_it_paid(): void
    {
        config()->set('services.stripe.secret_key', 'sk_test_secret');
        config()->set('services.twilio.account_sid', '');
        [$user, $appointment] = $this->appointmentFixture();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.test/pay/cs_test_123',
            ], 200),
        ]);

        $this->actingAs($user)->post(route('appointments.payments.store', $appointment), [
            'amount' => '45.00',
            'method' => 'stripe',
            'complete_appointment' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointment_payments', [
            'appointment_id' => $appointment->id,
            'amount_cents' => 4500,
            'method' => 'stripe',
            'status' => 'pending',
            'stripe_checkout_session_id' => 'cs_test_123',
            'checkout_url' => 'https://checkout.stripe.test/pay/cs_test_123',
        ]);

        $paymentId = (int) DB::table('appointment_payments')->where('appointment_id', $appointment->id)->value('id');

        $this->postJson(route('stripe.webhook'), [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_intent' => 'pi_test_123',
                    'client_reference_id' => (string) $paymentId,
                    'metadata' => [
                        'type' => 'appointment_payment',
                        'payment_id' => (string) $paymentId,
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('appointment_payments', [
            'id' => $paymentId,
            'status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'event' => 'appointment_payment_receipt',
            'recipient' => 'rafael@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_nora_can_report_today_collected_payments(): void
    {
        [$user, $appointment] = $this->appointmentFixture();

        $this->actingAs($user)->post(route('appointments.payments.store', $appointment), [
            'amount' => '45.00',
            'method' => 'cash',
            'complete_appointment' => '1',
        ])->assertRedirect();

        $this->actingAs($user)
            ->getJson(route('nora-payments.today'))
            ->assertOk()
            ->assertJsonPath('total_cents', 4500)
            ->assertJsonPath('cash_cents', 4500)
            ->assertJsonPath('message', 'Hoy llevamos cobrado 45.00 dolares. En efectivo 45.00, por Stripe 0.00 y por otros metodos 0.00.');
    }

    private function appointmentFixture(): array
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'timezone' => 'Europe/Madrid',
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
            'email' => 'rafael@example.com',
        ]);
        $service = Service::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Manicure regular',
            'duration_minutes' => 45,
            'price_cents' => 4500,
            'is_active' => true,
        ]);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(15),
            'status' => 'confirmed',
            'source' => 'manual',
        ]);

        return [$user, $appointment];
    }
}
