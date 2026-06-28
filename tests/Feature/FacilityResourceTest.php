<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\FacilityResource;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacilityResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_capacity_prevents_overbooking_even_with_available_staff(): void
    {
        $this->travelTo('2026-06-28 08:00:00');
        $user = User::factory()->create(['is_active' => true]);
        $clinic = Clinic::create(['name' => 'Salon Test', 'country_code' => 'ES', 'timezone' => 'Europe/Madrid', 'subscription_status' => 'trial']);
        DB::table('clinic_users')->insert(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $resource = FacilityResource::create(['clinic_id' => $clinic->id, 'name' => 'Sillas de peluqueria', 'capacity' => 1, 'is_active' => true]);
        $service = Service::create(['clinic_id' => $clinic->id, 'facility_resource_id' => $resource->id, 'resource_units' => 1, 'name' => 'Corte', 'duration_minutes' => 60, 'is_active' => true]);
        $first = Stylist::create(['clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Ana', 'is_active' => true, 'is_internal' => false, 'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '18:00']);
        $second = Stylist::create(['clinic_id' => $clinic->id, 'service_id' => $service->id, 'name' => 'Luz', 'is_active' => true, 'is_internal' => false, 'work_days' => ['monday'], 'work_starts_at' => '08:00', 'work_ends_at' => '18:00']);
        $first->services()->sync([$service->id]); $second->services()->sync([$service->id]);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Marta', 'phone' => '+34600000001']);
        Appointment::create(['clinic_id' => $clinic->id, 'client_id' => $client->id, 'service_id' => $service->id, 'stylist_id' => $first->id, 'starts_at' => '2026-06-29 08:00:00', 'ends_at' => '2026-06-29 09:00:00', 'status' => 'confirmed']);

        $this->get(route('public-bookings.create', ['clinic' => $clinic, 'service_id' => $service->id, 'date' => '2026-06-29']))
            ->assertOk()->assertDontSee('value="2026-06-29 10:00:00"', false);

        $this->actingAs($user)->get('/ajustes#recursos')->assertOk()->assertSee('Puestos y equipamiento')->assertSee('Sillas de peluqueria');

        $this->actingAs($user)->post('/ajustes/recursos', ['resource_name' => '  SILLAS DE PELUQUERIA  ', 'capacity' => 4])
            ->assertRedirect('/ajustes#recursos')
            ->assertSessionHasErrors('resource_name');
        $this->assertSame(1, FacilityResource::where('clinic_id', $clinic->id)->count());
    }
}
