<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_page_is_only_available_to_super_admins(): void
    {
        $normalUser = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($normalUser)
            ->get(route('super-admin.costs'))
            ->assertForbidden();
    }

    public function test_super_admin_can_see_clinic_usage_costs(): void
    {
        config()->set('services.usage_costs.sms_usd', 0.02);
        config()->set('services.usage_costs.call_usd', 0.05);
        config()->set('services.usage_costs.email_usd', 0.01);
        config()->set('services.usage_costs.machine_monthly_usd', 30);

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'email' => 'aurora@example.com',
            'timezone' => 'America/New_York',
        ]);

        DB::table('notifications')->insert([
            [
                'clinic_id' => $clinic->id,
                'channel' => 'sms',
                'event' => 'appointment_reminder_sms',
                'recipient' => '+12135550123',
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clinic_id' => $clinic->id,
                'channel' => 'voice',
                'event' => 'appointment_reminder_call',
                'recipient' => '+12135550123',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clinic_id' => $clinic->id,
                'channel' => 'email',
                'event' => 'appointment_payment_receipt',
                'recipient' => 'client@example.com',
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('call_logs')->insert([
            'clinic_id' => $clinic->id,
            'from_phone' => '+12135550123',
            'to_phone' => '+12135550124',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.costs'))
            ->assertOk()
            ->assertSee('Salon Aurora')
            ->assertSee('$30.13');
    }

    public function test_a_clinic_request_records_resource_usage(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Salon Medido',
            'email' => 'medido@example.com',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->get(route('public-bookings.show', $clinic))->assertOk();

        $this->assertDatabaseHas('clinic_request_metrics', ['clinic_id' => $clinic->id]);
        $metric = DB::table('clinic_request_metrics')->where('clinic_id', $clinic->id)->first();

        $this->assertGreaterThan(0, $metric->duration_ms);
        $this->assertGreaterThan(0, $metric->disk_bytes);
    }

    public function test_machine_cost_is_allocated_to_the_clinic_with_recorded_usage(): void
    {
        config()->set('services.usage_costs.machine_monthly_usd', 40);

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $usedClinic = Clinic::query()->create(['name' => 'Salon Activo', 'timezone' => 'Europe/Madrid']);
        Clinic::query()->create(['name' => 'Salon Inactivo', 'timezone' => 'Europe/Madrid']);

        DB::table('clinic_request_metrics')->insert([
            'clinic_id' => $usedClinic->id,
            'duration_ms' => 500,
            'memory_bytes' => 1048576,
            'disk_bytes' => 2048,
            'recorded_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.costs'))
            ->assertOk()
            ->assertViewHas('clinics', function ($clinics) use ($usedClinic): bool {
                $rows = $clinics->keyBy(fn (array $row): int => $row['clinic']->id);

                return $rows[$usedClinic->id]['month']['machine_cost'] === 40.0
                    && $rows->first(fn (array $row): bool => $row['clinic']->id !== $usedClinic->id)['month']['machine_cost'] === 0.0;
            });
    }
}
