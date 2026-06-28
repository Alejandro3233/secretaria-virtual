<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\GoogleCalendarMapping;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\GoogleCalendarService;
use App\Services\AppointmentReminderCallService;
use App\Services\TwilioSmsService;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_clinic_activity_excel_with_three_sheets(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Informe',
            'email' => 'informe@example.com',
            'timezone' => 'Europe/Madrid',
        ]);
        $otherClinic = Clinic::create([
            'name' => 'Otro Salon',
            'email' => 'otro@example.com',
            'timezone' => 'Europe/Madrid',
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);

        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Cliente Propio',
            'phone' => '+34111111111',
            'email' => 'cliente@example.com',
        ]);
        $otherClient = Client::create([
            'clinic_id' => $otherClinic->id,
            'first_name' => 'Cliente Ajeno',
            'phone' => '+34222222222',
        ]);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'confirmed',
            'reason' => 'Corte de prueba',
        ]);
        Appointment::create([
            'clinic_id' => $otherClinic->id,
            'client_id' => $otherClient->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'confirmed',
            'reason' => 'Dato privado',
        ]);

        DB::table('notifications')->insert([
            [
                'clinic_id' => $clinic->id,
                'client_id' => $client->id,
                'appointment_id' => $appointment->id,
                'channel' => 'voice',
                'event' => 'appointment_reminder_call',
                'recipient' => $client->phone,
                'status' => 'sent',
                'body' => 'Llamada realizada',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clinic_id' => $clinic->id,
                'client_id' => $client->id,
                'appointment_id' => $appointment->id,
                'channel' => 'sms',
                'event' => 'appointment_reminder_sms',
                'recipient' => $client->phone,
                'status' => 'sent',
                'body' => 'SMS enviado',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get('/informes/actividad');

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contents = $response->getContent();
        $this->assertStringStartsWith('PK', $contents);
        $this->assertStringContainsString('name="Citas"', $contents);
        $this->assertStringContainsString('name="Llamadas"', $contents);
        $this->assertStringContainsString('name="SMS"', $contents);
        $this->assertStringContainsString('Cliente Propio', $contents);
        $this->assertStringNotContainsString('Cliente Ajeno', $contents);
    }

    public function test_google_calendar_dates_are_normalized_before_storage(): void
    {
        $service = new GoogleCalendarService();
        $method = new \ReflectionMethod($service, 'eventDateToCarbon');
        $date = new EventDateTime([
            'dateTime' => '2026-06-15T17:15:00+02:00',
            'timeZone' => 'Europe/Madrid',
        ]);

        $normalized = $method->invoke($service, $date);

        $this->assertSame('UTC', $normalized->getTimezone()->getName());
        $this->assertSame('2026-06-15 15:15:00', $normalized->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-15 17:15:00', $normalized->copy()->timezone('Europe/Madrid')->format('Y-m-d H:i:s'));
    }

    public function test_google_internal_stylist_is_visible_in_schedule_but_hidden_from_clients(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Google',
            'subscription_status' => 'trial',
            'google_connected_at' => now(),
            'google_last_synced_at' => now(),
            'google_ever_synced_at' => now(),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $sofia = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Sofia', 'is_active' => true]);

        $method = new \ReflectionMethod(new GoogleCalendarService(), 'calendarStylist');
        $google = $method->invoke(new GoogleCalendarService(), $clinic);

        $this->assertTrue($google->is_internal);
        $this->assertSame('Google', $google->name);

        $this->actingAs($user)->get('/agenda?view=day')
            ->assertOk()
            ->assertSee('Google')
            ->assertSee('value="'.$google->id.'"', false)
            ->assertSee('Sofia');

        $this->get(route('public-bookings.show', $clinic))
            ->assertOk()
            ->assertSee('Sofia')
            ->assertDontSee('Control interno de Google Calendar');

        $clinic->update(['google_connected_at' => null, 'google_last_synced_at' => null]);
        $this->actingAs($user)->get('/agenda?view=day')
            ->assertOk()
            ->assertSee('value="'.$google->id.'"', false)
            ->assertSee('Google (desconectado)');
        $this->actingAs($user)->get('/personal')
            ->assertOk()
            ->assertSee('Control interno de Google Calendar')
            ->assertSee('Google desconectado');

        $clinic->update(['google_ever_synced_at' => null]);
        $this->actingAs($user)->get('/agenda?view=day')
            ->assertOk()
            ->assertDontSee('value="'.$google->id.'"', false);

        $clinic->update(['google_connected_at' => now(), 'google_last_synced_at' => now(), 'google_ever_synced_at' => now()]);

        $client = Client::create(['clinic_id' => $clinic->id, 'first_name' => 'Cliente', 'phone' => '+15550123']);
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Corte', 'duration_minutes' => 30]);
        $sofia->services()->sync([$service->id]);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'stylist_id' => $sofia->id,
            'starts_at' => now()->next(\Carbon\Carbon::MONDAY)->setTime(12, 0),
            'ends_at' => now()->next(\Carbon\Carbon::MONDAY)->setTime(12, 30),
            'status' => 'confirmed',
        ]);
        $token = app(AppointmentReminderCallService::class)->tokenFor($appointment);

        $this->get(route('public-reschedule.show', [$appointment, $token, 'date' => $appointment->starts_at->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('- Sofia')
            ->assertDontSee('- Google');
    }

    public function test_google_calendar_settings_assign_detected_calendars_to_stylists(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Calendarios',
            'subscription_status' => 'trial',
            'google_calendar_summary' => 'Calendario principal',
            'google_calendar_organization_mode' => 'existing',
            'google_connected_at' => now(),
            'google_ever_synced_at' => now(),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $stylist = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Sofia', 'is_active' => true]);
        $internal = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Google', 'is_active' => true, 'is_internal' => true]);
        $mapping = GoogleCalendarMapping::create([
            'clinic_id' => $clinic->id,
            'google_calendar_id' => 'sofia@example.com',
            'google_calendar_name' => 'Agenda Sofia',
            'access_role' => 'owner',
            'is_available' => true,
            'is_enabled' => false,
            'last_detected_at' => now(),
        ]);

        $this->actingAs($user)->get('/ajustes#google-calendar')
            ->assertOk()
            ->assertSee('Google Calendar y especialistas')
            ->assertSee('¿Cómo quieres organizar Google Calendar?')
            ->assertSee('Un calendario por especialista')
            ->assertSee('Un único calendario para todo el salón')
            ->assertSee('Asignar calendarios existentes')
            ->assertSee('Agenda Sofia')
            ->assertSee('Sofia')
            ->assertDontSee('value="'.$internal->id.'"', false);

        $this->actingAs($user)->put(route('google-calendar.mappings.update'), [
            'calendars' => [
                $mapping->id => [
                    'stylist_id' => $stylist->id,
                    'enabled' => '1',
                ],
            ],
        ])->assertRedirect('/ajustes#google-calendar');

        $this->assertDatabaseHas('google_calendar_mappings', [
            'id' => $mapping->id,
            'stylist_id' => $stylist->id,
            'is_enabled' => true,
        ]);

        $appointment = new Appointment([
            'clinic_id' => $clinic->id,
            'stylist_id' => $stylist->id,
        ]);
        $appointment->setRelation('clinic', $clinic);
        $method = new \ReflectionMethod(new GoogleCalendarService(), 'calendarIdForAppointment');

        $this->assertSame('sofia@example.com', $method->invoke(new GoogleCalendarService(), $appointment));
    }

    public function test_google_reconnection_recognizes_own_appointment_without_duplicating_it(): void
    {
        $clinic = Clinic::create(['name' => 'Salon sin duplicados']);
        $stylist = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Sofia', 'is_active' => true]);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'phone' => '+34111111111',
        ]);
        $service = Service::create([
            'clinic_id' => $clinic->id,
            'name' => 'Corte',
            'duration_minutes' => 60,
        ]);
        $startsAt = now()->addDay()->startOfHour();
        $endsAt = $startsAt->copy()->addHour();
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'stylist_id' => $stylist->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source' => 'web',
            'google_calendar_id' => 'primary',
            'google_calendar_event_id' => 'evento-original',
            'google_sync_status' => 'synced',
        ]);

        $eventWithInternalId = new Event([
            'id' => 'evento-despues-de-reconectar',
            'summary' => 'Corte - Ana Lopez',
            'extendedProperties' => [
                'private' => ['secretaria_virtual_appointment_id' => (string) $appointment->id],
            ],
        ]);
        $method = new \ReflectionMethod(new GoogleCalendarService(), 'findAppointmentForGoogleEvent');
        $matched = $method->invoke(
            new GoogleCalendarService(),
            $clinic,
            $eventWithInternalId,
            'primary',
            $stylist,
            $startsAt,
            $endsAt
        );

        $this->assertSame($appointment->id, $matched?->id);

        $legacyEventWithoutInternalId = new Event([
            'id' => 'evento-antiguo-sin-id-interno',
            'summary' => 'Corte - Ana Lopez',
        ]);
        $legacyMatched = $method->invoke(
            new GoogleCalendarService(),
            $clinic,
            $legacyEventWithoutInternalId,
            'primary',
            $stylist,
            $startsAt,
            $endsAt
        );

        $this->assertSame($appointment->id, $legacyMatched?->id);
        $this->assertSame(1, Appointment::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_reminder_call_uses_nora_spanish_message(): void
    {
        $clinic = Clinic::create([
            'name' => 'Salon Bella',
            'email' => 'bella@example.com',
            'timezone' => 'Europe/Madrid',
        ]);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'phone' => '+12138697308',
        ]);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 15:20:00',
            'ends_at' => '2026-06-15 16:20:00',
            'status' => 'confirmed',
        ]);
        $service = new AppointmentReminderCallService($this->mock(TwilioSmsService::class));

        $this->assertSame(
            'Hola Rafael, soy Nora, la asistente virtual de Salon Bella. Te estamos llamando para recordarte que tienes una cita con nosotros el 15 de junio de 2026 a las 5 y 20 de la tarde. Si quieres modificar la cita, presiona 1 en tu telefono.',
            $service->messageFor($appointment),
        );
    }

    public function test_appointments_use_traffic_light_colors(): void
    {
        $reference = now()->startOfHour();

        $confirmed = new Appointment(['status' => 'confirmed', 'starts_at' => $reference->copy()->addHours(2)]);
        $pending = new Appointment(['status' => 'pending', 'starts_at' => $reference->copy()->addHours(30)]);
        $light = new Appointment(['status' => 'pending', 'starts_at' => $reference->copy()->addHours(18)]);
        $medium = new Appointment(['status' => 'pending', 'starts_at' => $reference->copy()->addHours(9)]);
        $high = new Appointment(['status' => 'pending', 'starts_at' => $reference->copy()->addHours(3)]);
        $cancelled = new Appointment(['status' => 'cancelled', 'starts_at' => $reference->copy()->addHour()]);

        $this->assertSame('appointment-confirmed', $confirmed->trafficLightClass($reference));
        $this->assertSame('appointment-pending', $pending->trafficLightClass($reference));
        $this->assertSame('appointment-urgent-light', $light->trafficLightClass($reference));
        $this->assertSame('appointment-urgent-medium', $medium->trafficLightClass($reference));
        $this->assertSame('appointment-urgent-high', $high->trafficLightClass($reference));
        $this->assertSame('appointment-cancelled', $cancelled->trafficLightClass($reference));
    }

    public function test_schedule_shows_sent_and_responded_checks(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'email' => 'salon@example.com',
            'timezone' => config('app.timezone'),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Cliente',
            'phone' => '+15550100',
        ]);
        $sent = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'pending',
        ]);
        $responded = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'status' => 'confirmed',
        ]);

        DB::table('notifications')->insert([
            [
                'clinic_id' => $clinic->id,
                'client_id' => $client->id,
                'appointment_id' => $sent->id,
                'channel' => 'sms',
                'event' => 'appointment_created',
                'recipient' => $client->phone,
                'status' => 'sent',
                'body' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clinic_id' => $clinic->id,
                'client_id' => $client->id,
                'appointment_id' => $responded->id,
                'channel' => 'web',
                'event' => 'appointment_client_response',
                'recipient' => $client->phone,
                'status' => 'received',
                'body' => 'confirm',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->get('/agenda?view=week&date='.now()->addDay()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('SMS o correo enviado')
            ->assertSee('El cliente respondio');

        $this->actingAs($user)
            ->get('/citas?period=month&date='.now()->addDay()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('SMS o correo enviado')
            ->assertSee('El cliente respondio');
    }

    public function test_authenticated_user_can_cancel_an_appointment_without_deleting_it(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'email' => 'salon@example.com',
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Cliente',
            'phone' => '+15550101',
        ]);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->put("/citas/{$appointment->id}/cancelar")
            ->assertRedirect('/citas');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_day_filter_only_shows_today_appointments(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'email' => 'salon@example.com',
            'timezone' => config('app.timezone'),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Cliente',
            'phone' => '+15550102',
        ]);
        Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->setTime(12, 0),
            'ends_at' => now()->setTime(13, 0),
            'status' => 'confirmed',
            'reason' => 'Cita de hoy',
        ]);
        Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay()->setTime(12, 0),
            'ends_at' => now()->addDay()->setTime(13, 0),
            'status' => 'confirmed',
            'reason' => 'Cita de manana',
        ]);

        $this->actingAs($user)
            ->get('/citas?period=day&date='.now()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Cita de hoy')
            ->assertDontSee('Cita de manana');
    }

    public function test_appointment_period_navigation_uses_selected_date(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Test',
            'email' => 'salon@example.com',
            'timezone' => config('app.timezone'),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get('/citas?period=month&date=2026-07-15')
            ->assertOk()
            ->assertSee('Julio de 2026')
            ->assertSee('date=2026-06-15', false)
            ->assertSee('date=2026-08-15', false)
            ->assertDontSee('Todas');
    }

    public function test_console_shows_expected_revenue_from_services_for_selected_day(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Ganancias',
            'timezone' => config('app.timezone'),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Cliente',
            'phone' => '+15550177',
        ]);
        $service = Service::create([
            'clinic_id' => $clinic->id,
            'name' => 'Servicio rentable',
            'duration_minutes' => 60,
            'price_cents' => 7500,
        ]);
        $secondService = Service::create([
            'clinic_id' => $clinic->id,
            'name' => 'Segundo servicio',
            'duration_minutes' => 60,
            'price_cents' => 5000,
        ]);
        $sofia = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Sofia', 'is_active' => true]);
        $juan = Stylist::create(['clinic_id' => $clinic->id, 'name' => 'Juan', 'is_active' => true]);
        $date = now()->addDay()->startOfDay();

        foreach ([
            ['confirmed', 10, $service->id, $sofia->id],
            ['pending', 12, $secondService->id, $juan->id],
            ['cancelled', 14, $service->id, $sofia->id],
        ] as [$status, $hour, $serviceId, $stylistId]) {
            Appointment::create([
                'clinic_id' => $clinic->id,
                'client_id' => $client->id,
                'service_id' => $serviceId,
                'stylist_id' => $stylistId,
                'starts_at' => $date->copy()->setTime($hour, 0),
                'ends_at' => $date->copy()->setTime($hour + 1, 0),
                'status' => $status,
            ]);
        }

        $this->actingAs($user)
            ->get('/consola?date='.$date->toDateString().'&view=day')
            ->assertOk()
            ->assertSee('Ganancias previstas')
            ->assertSee('$125.00')
            ->assertSee('$75.00')
            ->assertSee('$50.00')
            ->assertSee('Sofia')
            ->assertSee('Juan')
            ->assertSee('data-revenue-next', false)
            ->assertSee('Mini calendario')
            ->assertDontSee('Periodo operativo')
            ->assertDontSee('Comunicaciones');
    }

    public function test_guest_is_redirected_from_console_to_login(): void
    {
        $response = $this->get('/consola');

        $response->assertRedirect('/login');
    }

    public function test_user_can_register_and_reach_console(): void
    {
        $response = $this->post('/registro', [
            'name' => 'Admin',
            'last_name' => 'Demo',
            'email' => 'admin@example.com',
            'email_confirmation' => 'admin@example.com',
            'clinic_name' => 'Salon Demo',
            'clinic_address' => '123 Main Street',
            'clinic_phone' => '+15550142',
            'mobile_phone' => '+15550143',
            'mobile_phone_confirmation' => '+15550143',
            'country_code' => 'US',
            'password' => 'password-demo',
            'password_confirmation' => 'password-demo',
        ]);

        $response->assertRedirect('/consola');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('clinics', ['name' => 'Salon Demo']);
        $this->assertDatabaseCount('appointments', 0);
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

    public function test_super_admin_can_insert_database_record(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)
            ->post('/base-de-datos/clinics', [
                'name' => 'Salon Nuevo',
                'email' => 'nuevo@example.com',
                'phone' => '+12135550111',
                'timezone' => 'America/New_York',
            ])
            ->assertRedirect('/base-de-datos/clinics');

        $this->assertDatabaseHas('clinics', [
            'name' => 'Salon Nuevo',
            'email' => 'nuevo@example.com',
        ]);
    }

    public function test_super_admin_can_bulk_delete_database_records(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $first = Clinic::create(['name' => 'Salon Bulk 1']);
        $second = Clinic::create(['name' => 'Salon Bulk 2']);

        $this->actingAs($superAdmin)
            ->post('/base-de-datos/clinics/eliminar-seleccionados', [
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect('/base-de-datos/clinics');

        $this->assertDatabaseMissing('clinics', ['id' => $first->id]);
        $this->assertDatabaseMissing('clinics', ['id' => $second->id]);
    }

    public function test_super_admin_can_bulk_delete_clients_with_related_records(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $clinic = Clinic::create(['name' => 'Salon Clientes']);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
        ]);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'pending',
            'source' => 'manual',
        ]);

        DB::table('client_preferences')->insert([
            'client_id' => $client->id,
            'allergies' => 'Yodo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('appointment_activity_logs')->insert([
            'appointment_id' => $appointment->id,
            'action' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('call_logs')->insert([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'from_phone' => '+12135550123',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'channel' => 'sms',
            'event' => 'test',
            'recipient' => '+12135550123',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->post('/base-de-datos/clients/eliminar-seleccionados', [
                'ids' => [$client->id],
            ])
            ->assertRedirect('/base-de-datos/clients');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseMissing('client_preferences', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('call_logs', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('notifications', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('appointment_activity_logs', ['appointment_id' => $appointment->id]);
    }

    public function test_user_management_is_only_available_to_super_admins(): void
    {
        $normalUser = User::factory()->create(['is_super_admin' => false]);
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($normalUser)
            ->get('/gestion-usuarios')
            ->assertForbidden();

        $this->actingAs($normalUser)
            ->post('/gestion-usuarios', [])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/gestion-usuarios')
            ->assertOk()
            ->assertSee('Gestion de usuarios');
    }

    public function test_super_admin_can_create_and_assign_a_user(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $clinic = Clinic::create([
            'name' => 'Clinica de pruebas',
            'email' => 'pruebas@example.com',
        ]);

        $this->actingAs($superAdmin)
            ->post('/gestion-usuarios', [
                'name' => 'Usuario',
                'last_name' => 'Prueba',
                'email' => 'usuario.prueba@example.com',
                'mobile_phone' => '+34600000123',
                'password' => 'password-demo',
                'password_confirmation' => 'password-demo',
                'clinic_id' => $clinic->id,
                'role' => 'staff',
                'is_super_admin' => '0',
            ])
            ->assertRedirect('/gestion-usuarios');

        $user = User::query()->where('email', 'usuario.prueba@example.com')->firstOrFail();

        $this->assertFalse($user->is_super_admin);
        $this->assertDatabaseHas('clinic_users', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]);
    }

    public function test_super_admin_can_disable_and_enable_a_user(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $target = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'secret-password',
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('users.status', $target), ['is_active' => 0])
            ->assertRedirect(route('users.index', ['estado' => 'deshabilitados']));

        $this->assertFalse($target->fresh()->is_active);
        $this->post('/logout');
        $this->post('/login', ['email' => 'disabled@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->actingAs($superAdmin)
            ->patch(route('users.status', $target), ['is_active' => 1])
            ->assertRedirect(route('users.index', ['estado' => 'activos']));
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_deleted_user_is_removed_permanently(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $clinic = Clinic::create(['name' => 'Clinica usuarios']);
        $target = User::factory()->create(['email' => 'delete-forever@example.com']);
        $target->clinics()->attach($clinic->id, ['role' => 'staff']);

        $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('clinic_users', ['user_id' => $target->id, 'clinic_id' => $clinic->id]);
        $this->actingAs($superAdmin)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('delete-forever@example.com')
            ->assertDontSee('Historial de eliminados');
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
        $stylist->services()->sync([$service->id]);

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
            'first_name' => 'Laura',
            'last_name' => 'Perez',
            'phone' => '+15550001',
            'email' => 'laura@example.com',
        ]);
        $this->actingAs($user)
            ->get('/clientes')
            ->assertOk()
            ->assertSee('Laura Perez')
            ->assertSee('+15550001');
        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'stylist_id' => $stylist->id,
            'source' => 'web',
            'google_sync_status' => 'pending',
        ]);
    }

    public function test_schedule_creates_new_client_when_new_client_fields_replace_selected_client(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create(['name' => 'Salon Test']);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $existing = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12138697308',
        ]);

        $this->actingAs($user)
            ->post('/agenda/nueva-cita', [
                'client_id' => $existing->id,
                'client_first_name' => 'Ana',
                'client_last_name' => 'Lopez',
                'client_phone' => '+12135550124',
                'client_email' => 'ana@example.com',
                'starts_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
                'duration_minutes' => 45,
                'reason' => 'Tratamiento',
            ])
            ->assertRedirect('/agenda');

        $ana = Client::query()->where('clinic_id', $clinic->id)->where('phone', '+12135550124')->firstOrFail();
        $this->assertSame('Ana', $ana->first_name);
        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'client_id' => $ana->id,
            'reason' => 'Tratamiento',
        ]);
        $this->assertDatabaseMissing('appointments', [
            'clinic_id' => $clinic->id,
            'client_id' => $existing->id,
            'reason' => 'Tratamiento',
        ]);
    }

    public function test_appointment_cannot_be_moved_outside_stylist_working_hours(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::create([
            'name' => 'Salon Horarios',
            'email' => 'horarios@example.com',
            'timezone' => config('app.timezone'),
        ]);
        $user->clinics()->attach($clinic->id, ['role' => 'owner']);
        $stylist = Stylist::create([
            'clinic_id' => $clinic->id,
            'name' => 'Sofia Herrera',
            'work_days' => ['monday'],
            'work_starts_at' => '08:00',
            'work_ends_at' => '20:00',
            'is_active' => true,
        ]);
        $client = Client::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Laura',
            'phone' => '+15550009',
        ]);
        $monday = now()->startOfWeek()->addWeek();
        $originalStart = $monday->copy()->setTime(18, 0);
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'stylist_id' => $stylist->id,
            'starts_at' => $originalStart,
            'ends_at' => $originalStart->copy()->addHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->putJson('/agenda/citas/'.$appointment->id.'/mover', [
                'date' => $monday->format('Y-m-d'),
                'minutes' => 21 * 60,
                'stylist_id' => $stylist->id,
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'La cita queda fuera del horario de Sofia Herrera. Ese día trabaja de 8:00 AM a 8:00 PM. La cita debe comenzar y terminar dentro de su jornada.',
            ]);

        $this->assertSame($originalStart->format('Y-m-d H:i:s'), $appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
    }
}
