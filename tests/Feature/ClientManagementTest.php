<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_section_creates_and_displays_a_client(): void
    {
        [$user, $clinic] = $this->clinicUser();

        $response = $this->actingAs($user)->post('/clientes', [
            'first_name' => 'Ana', 'last_name' => 'Bello', 'phone' => '+34600111222',
            'email' => 'ana@example.com', 'address' => 'Calle Mayor 1',
            'notification_preference' => 'both', 'notes' => 'Prefiere tardes.',
        ]);

        $client = Client::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $response->assertRedirect(route('clients.show', $client));
        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()->assertSee('Ana Bello')->assertSee('Historial de asistencias al centro');
    }

    public function test_attendance_history_can_be_recorded(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+34600111222']);
        $service = Service::query()->create(['clinic_id' => $clinic->id, 'name' => 'Corte', 'duration_minutes' => 45, 'price_cents' => 3500]);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id, 'client_id' => $client->id, 'service_id' => $service->id,
            'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addMinutes(45), 'status' => 'confirmed',
        ]);

        $this->actingAs($user)->put(route('clients.attendance', [$client, $appointment]), ['attendance' => 'attended'])
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame('attended', $appointment->fresh()->status);
        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()->assertSee('Asistió')->assertSee('$35.00');
    }

    public function test_client_notes_can_be_updated_from_client_profile(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+34600111222']);

        $this->actingAs($user)
            ->put(route('clients.notes', $client), ['notes' => 'Es alergica a un medicamento.'])
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame('Es alergica a un medicamento.', $client->fresh()->notes);
        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Es alergica a un medicamento.');
    }

    public function test_clients_from_another_clinic_are_not_accessible(): void
    {
        [$user] = $this->clinicUser();
        $other = Clinic::query()->create(['name' => 'Otro salón']);
        $client = Client::query()->create(['clinic_id' => $other->id, 'first_name' => 'Privado', 'phone' => '+34900000000']);

        $this->actingAs($user)->get(route('clients.show', $client))->assertNotFound();
    }

    public function test_client_initials_work_with_separate_or_combined_names(): void
    {
        $this->assertSame('MR', (new Client(['first_name' => 'Miguel Alejandro', 'last_name' => 'Rodriguez Chela']))->initials());
        $this->assertSame('MC', (new Client(['first_name' => 'Maria Chela']))->initials());
        $this->assertSame('ÁG', (new Client(['first_name' => 'Álvaro', 'last_name' => 'Gómez']))->initials());
    }

    public function test_client_cancellation_notice_and_risk_are_displayed(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'phone' => '+34600111222']);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id, 'client_id' => $client->id,
            'starts_at' => now()->addHours(8), 'ends_at' => now()->addHours(9), 'status' => 'cancelled',
        ]);
        DB::table('notifications')->insert([
            'clinic_id' => $clinic->id, 'client_id' => $client->id, 'appointment_id' => $appointment->id,
            'channel' => 'web', 'event' => 'appointment_client_response', 'recipient' => $client->phone,
            'status' => 'received', 'body' => 'cancel', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('El cliente canceló 8 h antes')
            ->assertSee('Riesgo de cancelación')
            ->assertSee('Medio');

        $lateAppointment = Appointment::query()->create([
            'clinic_id' => $clinic->id, 'client_id' => $client->id,
            'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3), 'status' => 'cancelled',
        ]);
        DB::table('notifications')->insert([
            'clinic_id' => $clinic->id, 'client_id' => $client->id, 'appointment_id' => $lateAppointment->id,
            'channel' => 'web', 'event' => 'appointment_client_response', 'recipient' => $client->phone,
            'status' => 'received', 'body' => 'cancel', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()->assertSee('Muy alto');
    }

    public function test_client_can_be_marked_as_favorite_or_vip(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'phone' => '+34600111222',
        ]);

        $this->actingAs($user)
            ->put(route('clients.loyalty', $client), ['loyalty_level' => 1])
            ->assertRedirect(route('clients.show', $client));
        $this->assertSame(1, $client->fresh()->loyalty_level);

        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()->assertSee('Favorito');

        $this->actingAs($user)
            ->put(route('clients.loyalty', $client), ['loyalty_level' => 2])
            ->assertRedirect(route('clients.show', $client));
        $this->assertSame(2, $client->fresh()->loyalty_level);

        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()->assertSee('Cliente VIP');
    }

    public function test_client_directory_can_be_filtered_by_favorites_or_vip(): void
    {
        [$user, $clinic] = $this->clinicUser();
        Client::query()->create(['clinic_id' => $clinic->id, 'first_name' => 'Cliente Normal', 'phone' => '+34600000001']);
        Client::query()->create(['clinic_id' => $clinic->id, 'first_name' => 'Cliente Favorito', 'phone' => '+34600000002', 'loyalty_level' => 1]);
        Client::query()->create(['clinic_id' => $clinic->id, 'first_name' => 'Cliente VIP', 'phone' => '+34600000003', 'loyalty_level' => 2]);

        $this->actingAs($user)->get(route('clients.index', ['categoria' => 'favoritos']))
            ->assertOk()
            ->assertSee('Cliente Favorito')
            ->assertDontSee('Cliente Normal')
            ->assertDontSee('Cliente VIP');

        $this->actingAs($user)->get(route('clients.index', ['categoria' => 'vip']))
            ->assertOk()
            ->assertSee('Cliente VIP')
            ->assertDontSee('Cliente Normal')
            ->assertDontSee('Cliente Favorito');
    }

    private function clinicUser(): array
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create(['name' => 'Salón Aurora', 'timezone' => 'Europe/Madrid']);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id, 'user_id' => $user->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $clinic];
    }
}
