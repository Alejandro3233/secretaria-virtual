<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\ScheduleOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_proposes_the_latest_compatible_appointment_for_the_earliest_gap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00', 'UTC'));
        $timezone = 'Europe/Madrid';
        $clinic = Clinic::create(['name' => 'Salon', 'email' => 'salon@example.com', 'timezone' => $timezone]);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'work_days' => ['monday'],
            'work_starts_at' => '08:00',
            'work_ends_at' => '20:00',
            'is_active' => true,
        ]);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Miguel', 'phone' => '+12135550123']);
        $date = Carbon::parse('2026-06-22', $timezone);
        $create = function (string $start, string $end, string $reason) use ($clinic, $stylist, $client, $date): Appointment {
            return Appointment::create([
                'clinic_id' => $clinic->id,
                'stylist_id' => $stylist->id,
                'client_id' => $client->id,
                'starts_at' => $date->copy()->setTimeFromTimeString($start)->timezone(config('app.timezone')),
                'ends_at' => $date->copy()->setTimeFromTimeString($end)->timezone(config('app.timezone')),
                'status' => 'pending',
                'reason' => $reason,
            ]);
        };

        $create('08:02', '11:02', 'Mechas');
        $create('11:05', '12:20', 'Maquillaje');
        $create('12:20', '13:05', 'Manicure');
        $create('14:45', '15:00', 'Cejas');
        $create('15:05', '16:05', 'Balayage');
        $create('16:05', '18:05', 'Uñas');
        $eyebrows = $create('18:00', '18:15', 'Cejas');
        $late = $create('18:35', '19:35', 'Pedicure');

        $appointments = Appointment::with(['client', 'service', 'stylist'])->get()->each(function (Appointment $appointment): void {
            $appointment->starts_at = $appointment->starts_at->timezone(config('app.timezone'));
            $appointment->ends_at = $appointment->ends_at?->timezone(config('app.timezone'));
        });
        $suggestion = app(ScheduleOptimizationService::class)->suggestion($clinic, $date, $appointments);

        if (! $suggestion) {
            $this->fail(json_encode([
                'date' => $date->toIso8601String(),
                'day' => $date->englishDayOfWeek,
                'appointments' => $appointments->map(fn ($item) => [$item->starts_at->toIso8601String(), $item->ends_at?->toIso8601String(), $item->client?->phone])->all(),
            ]));
        }

        $this->assertSame($late->id, $suggestion['appointment']->id);
        $this->assertSame('13:05', $suggestion['proposed_start']->format('H:i'));
        $this->assertSame('14:05', $suggestion['proposed_end']->format('H:i'));

        DB::table('notifications')->insert([
            'clinic_id' => $clinic->id, 'client_id' => $client->id, 'appointment_id' => $late->id,
            'channel' => 'sms', 'event' => 'appointment_optimization_offer', 'recipient' => $client->phone,
            'status' => 'sent', 'body' => json_encode([
                'proposed_start' => $suggestion['proposed_start']->toIso8601String(),
                'proposed_stylist_id' => $stylist->id,
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $nextSuggestion = app(ScheduleOptimizationService::class)->suggestion($clinic, $date, $appointments);

        $this->assertSame($eyebrows->id, $nextSuggestion['appointment']->id);
        $this->assertSame('14:05', $nextSuggestion['proposed_start']->format('H:i'));
    }

    public function test_client_can_open_and_accept_signed_earlier_time_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00', 'UTC'));
        $clinic = Clinic::create(['name' => 'Salon', 'email' => 'salon@example.com', 'timezone' => 'UTC']);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'work_days' => ['monday'],
            'work_starts_at' => '08:00',
            'work_ends_at' => '20:00',
            'is_active' => true,
        ]);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Miguel', 'phone' => '+12135550123']);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'stylist_id' => $stylist->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-22 18:35:00',
            'ends_at' => '2026-06-22 19:35:00',
            'status' => 'pending',
            'reason' => 'Pedicure',
        ]);
        $target = Carbon::parse('2026-06-22 13:05:00', 'UTC');
        $url = URL::temporarySignedRoute('schedule-optimization.show', now()->addHours(48), [
            'appointment' => $appointment->id,
            'target' => $target->timestamp,
        ]);

        $this->get($url)->assertOk()->assertSee('Aceptar el horario más temprano');
        $this->post($url)->assertOk()->assertSee('Cita adelantada');
        $this->assertSame('2026-06-22 13:05:00', $appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_client_gets_another_option_when_proposed_slot_was_just_taken(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00', 'UTC'));
        $clinic = Clinic::create(['name' => 'Salon', 'email' => 'salon2@example.com', 'timezone' => 'UTC']);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id, 'name' => 'Sofia', 'work_days' => ['monday'],
            'work_starts_at' => '08:00', 'work_ends_at' => '20:00', 'is_active' => true,
        ]);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Miguel', 'phone' => '+12135550123']);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id, 'stylist_id' => $stylist->id, 'client_id' => $client->id,
            'starts_at' => '2026-06-22 18:35:00', 'ends_at' => '2026-06-22 19:35:00',
            'status' => 'pending', 'reason' => 'Pedicure',
        ]);
        $target = Carbon::parse('2026-06-22 13:05:00', 'UTC');
        $url = URL::temporarySignedRoute('schedule-optimization.show', now()->addHours(48), [
            'appointment' => $appointment->id, 'target' => $target->timestamp,
        ]);
        Appointment::create([
            'clinic_id' => $clinic->id, 'stylist_id' => $stylist->id, 'client_id' => $client->id,
            'starts_at' => '2026-06-22 13:05:00', 'ends_at' => '2026-06-22 14:05:00',
            'status' => 'pending', 'reason' => 'Nueva reserva',
        ]);

        $this->post($url)
            ->assertOk()
            ->assertSee('Encontramos otro horario disponible')
            ->assertSee('Revisar nuevo horario');
        $this->assertSame('2026-06-22 18:35:00', $appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_late_appointment_can_be_offered_to_another_qualified_stylist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00', 'UTC'));
        $clinic = Clinic::create(['name' => 'Salon', 'email' => 'salon3@example.com', 'timezone' => 'UTC']);
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Pedicure', 'duration_minutes' => 60, 'is_active' => true]);
        $otherPrimaryService = Service::create(['clinic_id' => $clinic->id, 'name' => 'Color', 'duration_minutes' => 60, 'is_active' => true]);
        $busy = Stylist::create(['clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Sofia', 'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '20:00', 'is_active' => true]);
        $available = Stylist::create(['clinic_id' => $clinic->id, 'service_id' => $otherPrimaryService->id, 'name' => 'Ana', 'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '20:00', 'is_active' => true]);
        $available->services()->sync([$service->id]);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Miguel', 'phone' => '+12135550123']);
        Appointment::create(['clinic_id' => $clinic->id, 'stylist_id' => $busy->id, 'client_id' => $client->id, 'starts_at' => '2026-06-22 08:00:00', 'ends_at' => '2026-06-22 18:00:00', 'status' => 'pending']);
        $late = Appointment::create(['clinic_id' => $clinic->id, 'service_id' => $service->id, 'stylist_id' => $busy->id, 'client_id' => $client->id, 'starts_at' => '2026-06-22 18:35:00', 'ends_at' => '2026-06-22 19:35:00', 'status' => 'pending']);

        $suggestion = app(ScheduleOptimizationService::class)->suggestion($clinic, Carbon::parse('2026-06-22', 'UTC'), collect());

        $this->assertSame($late->id, $suggestion['appointment']->id);
        $this->assertSame($available->id, $suggestion['stylist']->id);
        $this->assertSame('08:00', $suggestion['proposed_start']->format('H:i'));
    }
}
