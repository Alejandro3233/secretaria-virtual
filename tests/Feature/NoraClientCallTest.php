<?php

namespace Tests\Feature;

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
            ->assertSee('Te estamos llamando desde el salon.', false);
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
