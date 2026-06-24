<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\Services\CallForwardingInstructionService;
use App\Services\CallForwardingOnboardingService;
use App\Services\TwilioSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CallForwardingInstructionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_and_saves_no_answer_forwarding_instructions(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'phone' => '+34911222333',
            'country_code' => 'ES',
            'twilio_phone_number' => '+34910000000',
        ]);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sms = Mockery::mock(TwilioSmsService::class);
        $sms->shouldReceive('send')
            ->once()
            ->with('+34600000000', Mockery::on(fn (string $body) => str_contains($body, '**61*+34910000000**20#') && str_contains($body, '##61#')))
            ->andReturn('SM-forwarding-1');
        $this->app->instance(TwilioSmsService::class, $sms);

        $this->actingAs($user)->post('/ajustes/desvio-llamadas/instrucciones', [
            'mode' => 'no_answer',
            'ring_seconds' => 20,
            'recipient' => '+34600000000',
            'operator' => 'movistar',
        ])->assertRedirect('/ajustes#servicios');

        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $clinic->id,
            'event' => 'call_forwarding_instructions',
            'status' => 'sent',
            'provider_message_id' => 'SM-forwarding-1',
        ]);
        $preferences = $clinic->fresh()->notification_preferences;
        $this->assertSame('no_answer', $preferences['call_forwarding_mode']);
        $this->assertSame(20, $preferences['call_forwarding_ring_seconds']);
        $this->assertSame('ES', $preferences['call_forwarding_country']);
        $this->assertSame('movistar', $preferences['call_forwarding_operator']);
    }

    public function test_outside_hours_message_explains_manual_activation(): void
    {
        $message = app(CallForwardingInstructionService::class)
            ->message('outside_hours', '+34910000000', 20, 'ES', 'movistar');

        $this->assertStringContainsString('**21*+34910000000#', $message);
        $this->assertStringContainsString('##21#', $message);
        $this->assertStringContainsString('cada día', $message);
    }

    public function test_onboarding_opens_services_only_once(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'twilio_phone_number' => '+34910000000',
        ]);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $onboarding = app(CallForwardingOnboardingService::class);

        $this->assertSame('/ajustes#servicios', $onboarding->destinationFor($user));
        $this->assertSame('/consola', $onboarding->destinationFor($user));
        $this->assertNotEmpty($clinic->fresh()->notification_preferences['call_forwarding_onboarding_seen_at']);
    }

    public function test_it_detects_country_and_uses_verizon_instructions(): void
    {
        $service = app(CallForwardingInstructionService::class);

        $this->assertSame('US', $service->countryForPhone('+1 305 555 0100', 'US'));
        $this->assertSame('CA', $service->countryForPhone('+1 416 555 0100', 'CA'));
        $this->assertSame('ES', $service->countryForPhone('+34 911 222 333', 'US'));

        $message = $service->message('always', '+13055550199', 20, 'US', 'verizon');
        $this->assertStringContainsString('*72+13055550199', $message);
        $this->assertStringContainsString('*73', $message);
    }

    public function test_unknown_operator_receives_safe_operator_instructions(): void
    {
        $message = app(CallForwardingInstructionService::class)
            ->message('no_answer', '+13055550199', 20, 'US', 'other');

        $this->assertStringContainsString('Pide a tu operador', $message);
        $this->assertStringNotContainsString('**61*', $message);
    }
}
