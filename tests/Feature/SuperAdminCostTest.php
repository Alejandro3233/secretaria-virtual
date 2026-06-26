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
}
