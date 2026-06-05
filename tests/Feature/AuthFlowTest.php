<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\Stylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_console_to_login(): void
    {
        $response = $this->get('/consola');

        $response->assertRedirect('/login');
    }

    public function test_user_can_register_and_reach_console(): void
    {
        $response = $this->post('/registro', [
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
            'clinic_name' => 'Salon Demo',
            'clinic_phone' => '+15550142',
            'password' => 'password-demo',
            'password_confirmation' => 'password-demo',
        ]);

        $response->assertRedirect('/consola');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('clinics', ['name' => 'Salon Demo']);
        $this->assertDatabaseCount('appointments', 3);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'secret-password',
        ]);

        $this->post('/login', [
            'email' => 'owner@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/consola');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_open_main_sidebar_pages(): void
    {
        $user = User::factory()->create();

        foreach (['/agenda', '/citas', '/consola', '/ajustes'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_login_page_has_google_login_option(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('/auth/google/redirect');
    }

    public function test_schedule_page_prompts_google_calendar_connection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/agenda')
            ->assertOk()
            ->assertSee('Conectar Google Calendar');
    }

    public function test_user_can_exist_with_google_without_password(): void
    {
        $user = User::create([
            'name' => 'Google User',
            'email' => 'google@example.com',
            'google_id' => 'google-123',
            'avatar_url' => 'https://example.com/avatar.png',
            'email_verified_at' => now(),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'google@example.com',
            'google_id' => 'google-123',
        ]);
    }

    public function test_register_shows_registered_user_message_for_existing_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/registro', [
            'name' => 'Admin Demo',
            'email' => 'taken@example.com',
            'clinic_name' => 'Salon Demo',
            'clinic_phone' => '+15550142',
            'password' => 'password-demo',
            'password_confirmation' => 'password-demo',
        ])->assertSessionHasErrors([
            'email' => 'Usuario ya registrado. Inicia sesion o usa recuperar contrasena.',
        ]);
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post('/recuperar-contrasena', [
            'email' => 'reset@example.com',
        ])->assertSessionHas('status', 'Te enviamos un enlace para recuperar tu contrasena.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_database_button_is_only_visible_for_super_admin(): void
    {
        $normalUser = User::factory()->create(['is_super_admin' => false]);
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($normalUser)
            ->get('/consola')
            ->assertOk()
            ->assertDontSee('Base de datos');

        $this->actingAs($normalUser)
            ->get('/base-de-datos')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/consola')
            ->assertOk()
            ->assertSee('Base de datos');

        $this->actingAs($superAdmin)
            ->get('/base-de-datos')
            ->assertOk()
            ->assertSee('users');
    }

    public function test_super_admin_can_update_database_record(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $target = User::factory()->create(['name' => 'Original Name']);

        $this->actingAs($superAdmin)
            ->post("/base-de-datos/users/{$target->id}", [
                'name' => 'Updated Name',
                'email' => $target->email,
                'google_id' => $target->google_id,
                'avatar_url' => $target->avatar_url,
                'email_verified_at' => $target->email_verified_at,
                'is_super_admin' => 0,
            ])
            ->assertRedirect('/base-de-datos/users');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_cancelled_google_appointments_are_hidden_from_schedule(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'email' => 'salon@example.com',
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'phone' => '+15550142',
        ]);

        Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->startOfWeek()->setTime(12, 30),
            'ends_at' => now()->startOfWeek()->setTime(14, 30),
            'status' => 'cancelled',
            'source' => 'google_calendar',
            'reason' => 'Color raiz + blower - Maria Lopez',
            'google_calendar_event_id' => 'google-event-1',
        ]);

        $this->actingAs($user)
            ->get('/agenda')
            ->assertOk()
            ->assertDontSee('Color raiz + blower - Maria Lopez');
    }

    public function test_authenticated_user_can_create_appointment_from_schedule(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'email' => 'salon@example.com',
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $service = Service::create([
            'clinic_id' => $clinic->id,
            'name' => 'Corte',
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/agenda/nueva-cita', [
                'client_first_name' => 'Laura',
                'client_last_name' => 'Perez',
                'client_phone' => '+15550001',
                'client_email' => 'laura@example.com',
                'service_id' => $service->id,
                'stylist_id' => $stylist->id,
                'starts_at' => now()->startOfWeek()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
                'duration_minutes' => 45,
                'status' => 'confirmed',
                'reason' => 'Corte',
                'chair_station' => 'Silla 1',
            ])
            ->assertRedirect('/agenda');

        $this->assertDatabaseHas('clients', [
            'clinic_id' => $clinic->id,
            'phone' => '+15550001',
        ]);
        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'stylist_id' => $stylist->id,
            'source' => 'web',
            'google_sync_status' => 'pending',
        ]);
    }
}
