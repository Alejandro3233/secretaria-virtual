<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceAddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_addons_are_configurable_and_saved_with_public_booking(): void
    {
        $this->travelTo('2026-06-28 08:00:00');
        $user = User::factory()->create(['is_active' => true]);
        $clinic = Clinic::create(['name' => 'Salon Test', 'country_code' => 'ES', 'timezone' => 'Europe/Madrid', 'subscription_status' => 'trial']);
        DB::table('clinic_users')->insert(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Manicure gel', 'duration_minutes' => 60, 'price_cents' => 2500, 'is_active' => true]);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Ana', 'is_active' => true, 'is_internal' => false,
            'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '18:00',
        ]);
        $stylist->services()->sync([$service->id]);

        $this->actingAs($user)->post("/personal/servicios/{$service->id}/extras", [
            'addon_name' => 'Retirada de esmalte', 'addon_price' => '3.00',
        ])->assertRedirect('/personal/servicios?extras='.$service->id.'#service-'.$service->id);
        $addon = $service->addons()->firstOrFail();

        $this->get(route('public-bookings.create', ['clinic' => $clinic, 'service_id' => $service->id, 'date' => '2026-06-29']))
            ->assertOk()->assertSee('Retirada de esmalte')->assertSee('+3.00 EUR');

        $this->post(route('public-bookings.store', $clinic), [
            'service_id' => $service->id,
            'stylist_id' => $stylist->id,
            'addon_ids' => [$addon->id],
            'starts_at' => '2026-06-29 10:00:00',
            'first_name' => 'Marta',
            'phone' => '+34600000001',
        ])->assertRedirect("/salones/{$clinic->id}/reservar");

        $appointment = Appointment::firstOrFail();
        $this->assertSame(300, $appointment->addons_total_cents);
        $this->assertSame('Retirada de esmalte', $appointment->selected_addons[0]['name']);
    }
}
