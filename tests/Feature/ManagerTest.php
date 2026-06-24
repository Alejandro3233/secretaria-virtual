<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_shows_business_reports_from_clinic_data(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create(['name' => 'Salon Manager', 'timezone' => config('app.timezone')]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+15551001', 'loyalty_level' => 2]);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'is_active' => true,
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
        ]);
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Color', 'duration_minutes' => 60, 'price_cents' => 8500, 'is_active' => true]);
        $date = now()->startOfMonth()->addDays(2)->setTime(10, 0);
        Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'stylist_id' => $stylist->id,
            'service_id' => $service->id,
            'starts_at' => $date,
            'ends_at' => $date->copy()->addHour(),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)->get('/manager?month='.$date->format('Y-m'))
            ->assertOk()
            ->assertSee('Análisis de rendimiento')
            ->assertSee('Informe de empresa')
            ->assertSee('Facturación')
            ->assertSee('Informe de profesionales')
            ->assertSee('Informe de clientes')
            ->assertSee('Informe de almacén')
            ->assertSee('Inventario')
            ->assertSee('$85.00')
            ->assertSee('Sofia')
            ->assertSee('Color');
    }

    public function test_inventory_can_be_created_and_adjusted_only_by_its_clinic(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create(['name' => 'Salon Inventario']);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);

        $this->actingAs($user)->post(route('manager.inventory.store'), [
            'name' => 'Champu',
            'sku' => 'CH-1',
            'category' => 'Cabello',
            'current_stock' => 5,
            'minimum_stock' => 2,
            'unit' => 'unidad',
            'cost' => 4.50,
            'sale_price' => 10,
        ])->assertRedirect('/manager#inventario');

        $item = InventoryItem::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $this->actingAs($user)->patch(route('manager.inventory.adjust', $item), [
            'adjustment' => -2,
        ])->assertRedirect('/manager#inventario');
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'current_stock' => 3]);

        $otherClinic = Clinic::create(['name' => 'Otro Salon']);
        $otherItem = InventoryItem::create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Producto ajeno',
            'current_stock' => 10,
            'minimum_stock' => 1,
            'unit' => 'unidad',
        ]);
        $this->actingAs($user)->patch(route('manager.inventory.adjust', $otherItem), [
            'adjustment' => 5,
        ])->assertNotFound();
        $this->assertDatabaseHas('inventory_items', ['id' => $otherItem->id, 'current_stock' => 10]);
    }
}
