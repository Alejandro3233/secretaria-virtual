<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoraClientCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.twilio.account_sid' => 'AC'.str_repeat('1', 32),
            'services.twilio.auth_token' => 'test-auth-token',
            'services.twilio.from' => '+16693156472',
        ]);
    }

    public function test_nora_can_call_a_client_found_by_similar_name(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA-client-call-1'], 201),
        ]);

        $response = $this->actingAs($user)->postJson(route('nora-client-calls.store'), [
            'name' => 'Rafel Rodriguez',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('client', 'Rafael Rodriguez');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC11111111111111111111111111111111/Calls.json'
                && ($data['To'] ?? null) === '+12135550123'
                && ($data['From'] ?? null) === '+16693156472'
                && ! isset($data['Url'])
                && str_contains((string) ($data['Twiml'] ?? ''), 'Hola Rafael, soy Nora');
        });

        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'channel' => 'voice',
            'event' => 'nora_client_call',
            'recipient' => '+12135550123',
            'status' => 'queued',
            'provider_message_id' => 'CA-client-call-1',
        ]);
    }

    public function test_client_call_twiml_uses_nora_message(): void
    {
        [, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
        ]);
        $token = hash_hmac('sha256', $client->id.'|'.$client->clinic_id.'|nora-client-call', config('app.key'));

        $response = $this->post(route('twilio.voice.client-call', [$client, $token]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->assertSee('Hola Rafael, soy Nora', false)
            ->assertSee('Te estamos llamando desde el salon.', false)
            ->assertSee('Un momento, te comunico.', false);
    }

    public function test_client_call_connects_to_browser_console_when_voice_is_configured(): void
    {
        config()->set([
            'services.twilio.api_key_sid' => 'SK'.str_repeat('2', 32),
            'services.twilio.api_key_secret' => 'test-api-secret',
            'services.twilio.twiml_app_sid' => 'AP'.str_repeat('3', 32),
        ]);

        [$user, $clinic] = $this->clinicUser();
        Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA-client-call-1'], 201),
        ]);

        $this->actingAs($user)->postJson(route('nora-client-calls.store'), [
            'name' => 'Rafael',
        ])->assertOk();

        Http::assertSent(function ($request) use ($clinic): bool {
            $twiml = (string) ($request->data()['Twiml'] ?? '');

            return str_contains($twiml, '<Dial answerOnBridge="true" timeout="30">')
                && str_contains($twiml, '<Client>')
                && str_contains($twiml, '<Identity>clinic-'.$clinic->id.'</Identity>')
                && str_contains($twiml, 'No pude comunicarte con el salon ahora mismo.');
        });
    }

    public function test_nora_can_remind_a_named_client_about_their_next_appointment(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
        ]);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay()->setTime(16, 0),
            'ends_at' => now()->addDay()->setTime(17, 0),
            'status' => 'pending',
            'source' => 'manual',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA-reminder-call-1'], 201),
        ]);

        $response = $this->actingAs($user)->postJson(route('nora-client-appointment-reminders.store'), [
            'name' => 'Rafael',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('client', 'Rafael Rodriguez')
            ->assertJsonPath('appointment_id', $appointment->id);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['To'] ?? null) === '+12135550123'
                && ($data['From'] ?? null) === '+16693156472'
                && isset($data['Url'])
                && str_contains((string) $data['Url'], '/twilio/voice/reminder/');
        });

        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'event' => 'appointment_reminder_call',
            'status' => 'queued',
            'provider_message_id' => 'CA-reminder-call-1',
        ]);
    }

    public function test_answered_appointment_reminder_call_confirms_the_appointment(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '+12135550123',
        ]);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay()->setTime(16, 0),
            'ends_at' => now()->addDay()->setTime(17, 0),
            'status' => 'pending',
            'source' => 'manual',
            'reminder_call_enabled' => true,
            'reminder_sms_enabled' => true,
        ]);

        DB::table('notifications')->insert([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'channel' => 'voice',
            'event' => 'appointment_reminder_call',
            'recipient' => $client->phone,
            'status' => 'queued',
            'provider_message_id' => 'CA-reminder-call-answered',
            'body' => 'recordatorio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('twilio.voice.reminder-status'), [
            'CallSid' => 'CA-reminder-call-answered',
            'CallStatus' => 'answered',
            'From' => '+16693156472',
            'To' => '+12135550123',
        ])->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'reminder_call_enabled' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'appointment_id' => $appointment->id,
            'channel' => 'voice',
            'event' => 'appointment_client_response',
            'status' => 'received',
            'body' => 'confirm',
        ]);

        $this->actingAs($user)
            ->get('/citas?period=day&date='.$appointment->starts_at->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Cliente ya fue llamado');

        $this->actingAs($user)->put('/citas/'.$appointment->id.'/recordatorio', [
            'reminder_call_enabled' => true,
            'reminder_sms_enabled' => true,
        ])->assertSessionHas('appointment_error', 'Cliente ya fue llamado.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'reminder_call_enabled' => false,
            'reminder_sms_enabled' => true,
        ]);
    }

    public function test_mia_can_append_internal_notes_to_a_client_found_by_name(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Rafael',
            'last_name' => 'Rodriguez',
            'phone' => '',
            'notes' => 'Prefiere citas por la tarde.',
        ]);

        $response = $this->actingAs($user)->postJson(route('nora-client-notes.store'), [
            'name' => 'Rafel Rodriguez',
            'note' => 'es alergico al yodo',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'saved')
            ->assertJsonPath('client', 'Rafael Rodriguez');

        $client->refresh();

        $this->assertStringContainsString('Prefiere citas por la tarde.', (string) $client->notes);
        $this->assertStringContainsString('es alergico al yodo', (string) $client->notes);
    }

    public function test_nora_can_notify_next_appointment_client_about_delay(): void
    {
        [$user, $clinic] = $this->clinicUser();
        $client = Client::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'phone' => '+12135550999',
        ]);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'starts_at' => now()->addMinutes(20),
            'ends_at' => now()->addMinutes(80),
            'status' => 'pending',
            'source' => 'manual',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA-delay-call-1'], 201),
        ]);

        $response = $this->actingAs($user)->postJson(route('nora-next-appointment-delay.store'), [
            'minutes' => 30,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('client', 'Ana Lopez')
            ->assertJsonPath('appointment_id', $appointment->id);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['To'] ?? null) === '+12135550999'
                && str_contains((string) ($data['Twiml'] ?? ''), 'Hola Ana, soy Nora')
                && str_contains((string) ($data['Twiml'] ?? ''), 'retraso aproximado de 30 minutos');
        });

        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $clinic->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'event' => 'nora_delay_call',
            'status' => 'queued',
            'provider_message_id' => 'CA-delay-call-1',
        ]);
    }

    private function clinicUser(): array
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'timezone' => 'Europe/Madrid',
        ]);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $clinic];
    }
}
