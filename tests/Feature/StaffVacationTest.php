<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Stylist;
use App\Models\User;
use App\Services\StylistScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffVacationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_assign_and_remove_stylist_vacations(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $stylist = Stylist::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post('/personal/'.$stylist->id.'/vacaciones', [
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-14',
            'reason' => 'Viaje familiar',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('stylist_vacations', [
            'stylist_id' => $stylist->id,
            'starts_on' => '2026-07-10 00:00:00',
            'ends_on' => '2026-07-14 00:00:00',
            'reason' => 'Viaje familiar',
        ]);

        $vacation = $stylist->vacations()->firstOrFail();

        $this->actingAs($user)
            ->get('/personal')
            ->assertOk()
            ->assertSee('10/07/2026 - 14/07/2026')
            ->assertSee('Viaje familiar');

        $this->actingAs($user)
            ->delete('/personal/'.$stylist->id.'/vacaciones/'.$vacation->id)
            ->assertRedirect('/personal');

        $this->assertDatabaseMissing('stylist_vacations', [
            'id' => $vacation->id,
        ]);
    }

    public function test_stylist_schedule_validation_blocks_vacation_days(): void
    {
        [, $clinic] = $this->clinicUser();
        $stylist = Stylist::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'work_starts_at' => '08:00',
            'work_ends_at' => '18:00',
            'is_active' => true,
        ]);
        $stylist->vacations()->create([
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-14',
            'reason' => 'Viaje familiar',
        ]);

        $message = app(StylistScheduleService::class)->validationMessage(
            $stylist,
            now()->parse('2026-07-13 10:00'),
            now()->parse('2026-07-13 11:00'),
        );

        $this->assertSame('Sofia esta de vacaciones del 10/07/2026 al 14/07/2026. Motivo: Viaje familiar.', $message);
    }

    public function test_day_schedule_marks_vacation_column_as_blocked(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $stylist = Stylist::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'work_days' => ['tuesday'],
            'is_active' => true,
        ]);
        $stylist->vacations()->create([
            'starts_on' => '2026-07-07',
            'ends_on' => '2026-07-09',
            'reason' => 'Descanso anual',
        ]);

        $this->actingAs($user)
            ->get('/agenda?date=2026-07-07&view=day')
            ->assertOk()
            ->assertSee('is-vacation', false)
            ->assertSee('data-unavailable="vacation"', false)
            ->assertSee('Vacaciones');
    }

    public function test_nora_can_query_stylist_vacations_by_name(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $stylist = Stylist::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Patricia',
            'is_active' => true,
        ]);
        $stylist->vacations()->create([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-07',
            'reason' => 'Descanso anual',
        ]);

        $this->actingAs($user)
            ->getJson(route('staff.vacations.index', ['name' => 'Patricia']))
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('stylist', 'Patricia')
            ->assertJsonPath('message', 'Las proximas vacaciones de Patricia son: 01/08/2026 al 07/08/2026, Descanso anual.');
    }

    private function clinicUser(): array
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'timezone' => 'Europe/Madrid',
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);

        return [$user, $clinic];
    }
}
