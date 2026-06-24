<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyBriefingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_nora_daily_briefing_is_created_once_and_can_be_marked_as_played(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00', 'UTC'));
        $user = User::factory()->create();
        $clinic = Clinic::create(['name' => 'Salon Nora', 'timezone' => 'UTC']);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'phone' => '+15550190',
            'loyalty_level' => 2,
        ]);
        $service = Service::create([
            'clinic_id' => $clinic->id,
            'name' => 'Color',
            'duration_minutes' => 60,
            'price_cents' => 5000,
        ]);

        foreach ([10, 12] as $hour) {
            Appointment::create([
                'clinic_id' => $clinic->id,
                'client_id' => $client->id,
                'service_id' => $service->id,
                'starts_at' => now()->setTime($hour, 0),
                'ends_at' => now()->setTime($hour + 1, 0),
                'status' => 'confirmed',
            ]);
        }

        $this->actingAs($user)->get('/consola')
            ->assertOk()
            ->assertSee('Activar Nora')
            ->assertDontSee('data-daily-briefing', false)
            ->assertDontSee('dailyBriefingShouldAutoPlay', false)
            ->assertSee('data-nora-listening', false);

        $this->assertDatabaseCount('daily_briefings', 1);
        $message = \App\Models\DailyBriefing::query()->firstOrFail()->message;
        $this->assertStringContainsString('Tenemos 2 citas programadas', $message);
        $this->assertStringContainsString('cliente VIP', $message);
        $this->assertStringContainsString('100.00 dólares', $message);
        $this->assertDatabaseHas('daily_briefings', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'briefing_date' => '2026-06-22 00:00:00',
            'played_at' => null,
        ]);

        $this->actingAs($user)->postJson(route('console.daily-briefing.played'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($user)->get('/consola')
            ->assertOk()
            ->assertDontSee('data-daily-briefing', false);
        $this->assertDatabaseCount('daily_briefings', 1);
        $this->assertNotNull(\App\Models\DailyBriefing::query()->first()->played_at);
    }
}
