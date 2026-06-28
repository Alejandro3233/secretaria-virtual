<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_image_can_be_uploaded_and_is_shown_publicly(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['is_active' => true]);
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'country_code' => 'ES',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'trial',
        ]);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post('/personal/servicios', [
            'name' => 'Balayage',
            'duration_minutes' => 120,
            'price' => 95,
            'is_active' => 1,
            'image' => UploadedFile::fake()->createWithContent(
                'balayage.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ])->assertRedirect('/personal/servicios');

        $service = Service::where('name', 'Balayage')->firstOrFail();
        Storage::disk('public')->assertExists($service->image_path);

        $this->get(route('public-bookings.show', $clinic))
            ->assertOk()
            ->assertSee('storage/'.$service->image_path, false);

        $this->get(route('public-bookings.create', ['clinic' => $clinic, 'service_id' => $service->id]))
            ->assertOk()
            ->assertSee('storage/'.$service->image_path, false);

        $service->update(['name' => 'Manicure gel']);
        $this->actingAs($user)->put('/personal/servicios/'.$service->id, [
            'name' => 'Manicure gel', 'duration_minutes' => 60, 'price' => 45, 'is_active' => 1,
            'sample_image' => 'manicure-gel.png',
        ])->assertRedirect('/personal/servicios');
        $this->assertSame('sample:manicure-gel.png', $service->fresh()->image_path);
        $this->get(route('public-bookings.show', $clinic))->assertSee('images/service-samples/manicure-gel.png', false);
    }
}
