<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\FlashCampaign;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FlashCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_draft_only_includes_clients_with_channel_consent(): void
    {
        [$user, $clinic, $service] = $this->context();
        Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+34600000001', 'email' => 'ana@example.com', 'marketing_email_consent_at' => now()]);
        Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Noa', 'phone' => '+34600000002', 'email' => 'noa@example.com']);

        $response = $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'Flash color', 'service_id' => $service->id, 'discount_percent' => 20,
            'expires_at' => now()->addDay()->format('Y-m-d H:i:s'), 'segment' => 'all',
            'channels' => ['email'], 'subject' => 'Oferta', 'message' => 'Reserva tu oferta.',
        ]);

        $campaign = FlashCampaign::firstOrFail();
        $response->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame(1, $campaign->recipients()->count());
        $this->assertSame(8000, $campaign->discounted_price_cents);
    }

    public function test_confirming_draft_sends_email_and_activates_campaign(): void
    {
        Mail::fake();
        [$user, $clinic, $service] = $this->context();
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+34600000001', 'email' => 'ana@example.com', 'marketing_email_consent_at' => now()]);
        $campaign = FlashCampaign::create([
            'clinic_id' => $clinic->id, 'service_id' => $service->id, 'created_by' => $user->id,
            'name' => 'Flash color', 'discount_percent' => 20, 'original_price_cents' => 10000,
            'discounted_price_cents' => 8000, 'segment' => 'all', 'channels' => ['email'],
            'subject' => 'Oferta', 'message' => 'Reserva tu oferta.', 'expires_at' => now()->addDay(), 'status' => 'draft',
        ]);
        $campaign->recipients()->create(['client_id' => $client->id, 'token' => fake()->uuid(), 'email' => $client->email, 'email_status' => 'pending', 'sms_status' => 'not_applicable']);

        $this->actingAs($user)->post(route('campaigns.send', $campaign))->assertRedirect(route('campaigns.show', $campaign));

        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertSame('sent', $campaign->recipients()->first()->email_status);
    }

    public function test_flash_offer_only_shows_and_accepts_dates_within_its_validity(): void
    {
        $this->travelTo('2026-06-27 10:00:00');
        [, $clinic, $service] = $this->context();
        Stylist::create([
            'clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Sofia',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'work_starts_at' => '08:00', 'work_ends_at' => '21:00', 'is_active' => true, 'is_internal' => false,
        ]);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+34600000001', 'email' => 'ana@example.com']);
        $campaign = FlashCampaign::create([
            'clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Flash color',
            'discount_percent' => 20, 'original_price_cents' => 10000, 'discounted_price_cents' => 8000,
            'segment' => 'all', 'channels' => ['email'], 'message' => 'Reserva tu oferta.',
            'expires_at' => '2026-06-28 18:00:00', 'status' => 'active',
        ]);
        $recipient = $campaign->recipients()->create([
            'client_id' => $client->id, 'token' => fake()->uuid(), 'email' => $client->email,
            'email_status' => 'sent', 'sms_status' => 'not_applicable',
        ]);

        $this->get(route('public-bookings.create', ['clinic' => $clinic, 'offer' => $recipient->token]))
            ->assertOk()
            ->assertSee('date=2026-06-28', false)
            ->assertDontSee('date=2026-06-29', false);

        $this->post(route('public-bookings.store', $clinic), [
            'service_id' => $service->id, 'starts_at' => '2026-06-29 10:00:00',
            'first_name' => 'Ana', 'phone' => '+34600000001', 'offer_token' => $recipient->token,
        ])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_staff_member_can_be_assigned_multiple_services(): void
    {
        [$user, $clinic, $service] = $this->context();
        $secondService = Service::create(['clinic_id' => $clinic->id, 'name' => 'Corte', 'duration_minutes' => 45, 'is_active' => true]);

        $this->actingAs($user)->post('/personal', [
            'name' => 'Sofia', 'service_ids' => [$service->id, $secondService->id],
            'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '17:00', 'is_active' => 1,
            'weekly_schedule' => [
                'monday' => ['enabled' => 1, 'start' => '09:00', 'end' => '20:00'],
                'saturday' => ['enabled' => 1, 'start' => '10:00', 'end' => '15:00'],
            ],
        ])->assertRedirect('/personal');

        $stylist = Stylist::where('name', 'Sofia')->firstOrFail();
        $this->assertEqualsCanonicalizing([$service->id, $secondService->id], $stylist->services()->pluck('services.id')->all());
        $this->assertSame('20:00', $stylist->weekly_schedule['monday']['end']);
        $this->assertSame('10:00', $stylist->weekly_schedule['saturday']['start']);
    }

    public function test_appointment_cannot_be_assigned_to_staff_without_the_service(): void
    {
        [$user, $clinic, $service] = $this->context();
        $otherService = Service::create(['clinic_id' => $clinic->id, 'name' => 'Corte', 'duration_minutes' => 45, 'is_active' => true]);
        $stylist = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Sofia', 'is_active' => true]);
        $stylist->services()->sync([$service->id]);

        $this->actingAs($user)->post('/agenda/nueva-cita', [
            'client_first_name' => 'Ana', 'client_phone' => '+34600000001',
            'service_id' => $otherService->id, 'stylist_id' => $stylist->id,
            'starts_at' => now($clinic->localTimezone())->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
        ])->assertSessionHasErrors('stylist_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_staff_daily_break_is_removed_from_public_availability(): void
    {
        $this->travelTo('2026-06-28 08:00:00');
        [, $clinic, $service] = $this->context();
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Sofia',
            'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '18:00',
            'break_starts_at' => '13:00', 'break_ends_at' => '14:00', 'is_active' => true, 'is_internal' => false,
        ]);
        $stylist->services()->sync([$service->id]);

        $response = $this->get(route('public-bookings.create', [
            'clinic' => $clinic, 'service_id' => $service->id, 'date' => '2026-06-29',
        ]))->assertOk();

        $response->assertSee('value="2026-06-29 12:00:00"', false)
            ->assertDontSee('value="2026-06-29 12:30:00"', false)
            ->assertDontSee('value="2026-06-29 13:00:00"', false)
            ->assertDontSee('value="2026-06-29 13:30:00"', false)
            ->assertSee('value="2026-06-29 14:00:00"', false);
    }

    public function test_public_availability_uses_the_schedule_configured_for_each_weekday(): void
    {
        $this->travelTo('2026-06-26 08:00:00');
        [, $clinic, $service] = $this->context();
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Sofia', 'is_active' => true,
            'weekly_schedule' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '20:00', 'break_start' => null, 'break_end' => null],
                'saturday' => ['enabled' => true, 'start' => '10:00', 'end' => '15:00', 'break_start' => null, 'break_end' => null],
            ],
        ]);
        $stylist->services()->sync([$service->id]);

        $saturday = $this->get(route('public-bookings.create', ['clinic' => $clinic, 'service_id' => $service->id, 'date' => '2026-06-27']))->assertOk();
        $saturday->assertDontSee('value="2026-06-27 09:00:00"', false)
            ->assertSee('value="2026-06-27 10:00:00"', false)
            ->assertSee('value="2026-06-27 14:00:00"', false)
            ->assertDontSee('value="2026-06-27 14:30:00"', false);

        $this->get(route('public-bookings.create', ['clinic' => $clinic, 'service_id' => $service->id, 'date' => '2026-06-29']))
            ->assertOk()->assertSee('value="2026-06-29 09:00:00"', false);
    }

    public function test_staff_avatar_can_be_uploaded_and_is_shown_publicly(): void
    {
        Storage::fake('public');
        [$user, $clinic, $service] = $this->context();

        $this->actingAs($user)->post('/personal', [
            'name' => 'Sofia', 'service_ids' => [$service->id], 'is_active' => 1,
        ])->assertRedirect('/personal');
        $stylist = Stylist::where('name', 'Sofia')->firstOrFail();

        $this->actingAs($user)->put('/personal/'.$stylist->id, [
            'name' => 'Sofia', 'service_ids' => [$service->id], 'is_active' => 1,
            'avatar' => UploadedFile::fake()->createWithContent('sofia.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
        ])->assertRedirect('/personal');

        $stylist->refresh();
        Storage::disk('public')->assertExists($stylist->avatar_path);
        $this->get(route('public-bookings.show', $clinic))->assertOk()
            ->assertSee('storage/'.$stylist->avatar_path, false);

        $this->actingAs($user)->put('/personal/'.$stylist->id, [
            'name' => 'Sofia', 'service_ids' => [$service->id], 'is_active' => 1,
            'preset_avatar' => 'avatar-woman-2.jpg',
        ])->assertRedirect('/personal');
        $this->assertSame('preset:avatar-woman-2.jpg', $stylist->fresh()->avatar_path);
        $this->get(route('public-bookings.show', $clinic))->assertSee('images/staff-avatars/avatar-woman-2.jpg', false);
    }

    public function test_daily_schedule_visually_marks_the_staff_break(): void
    {
        $this->travelTo('2026-06-27 08:00:00');
        [$user, $clinic] = $this->context();
        Stylist::create([
            'clinic_id' => $clinic->id, 'name' => 'Bertha', 'is_active' => true,
            'weekly_schedule' => ['monday' => [
                'enabled' => true, 'start' => '08:00', 'end' => '18:00',
                'break_start' => '14:00', 'break_end' => '15:00',
            ]],
        ]);

        $this->actingAs($user)->get('/agenda?date=2026-06-29&view=day')
            ->assertOk()->assertSee('Descanso 2:00 PM–3:00 PM', false);
    }

    private function context(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $clinic = Clinic::create(['name' => 'Salon Test', 'country_code' => 'ES', 'timezone' => 'Europe/Madrid', 'subscription_status' => 'trial']);
        DB::table('clinic_users')->insert(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Color', 'duration_minutes' => 60, 'price_cents' => 10000, 'is_active' => true]);

        return [$user, $clinic, $service];
    }
}
