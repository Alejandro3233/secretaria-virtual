<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrowserVoiceCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.twilio.account_sid' => 'AC'.str_repeat('1', 32),
            'services.twilio.api_key_sid' => 'SK'.str_repeat('2', 32),
            'services.twilio.api_key_secret' => 'test-secret',
            'services.twilio.twiml_app_sid' => 'AP'.str_repeat('3', 32),
            'services.twilio.browser_ring_timeout' => 18,
        ]);
    }

    public function test_incoming_call_rings_the_clinic_browser_before_nora(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Salón Aurora',
            'twilio_phone_number' => '+34910000000',
        ]);

        $response = $this->post('/twilio/voice/incoming', [
            'CallSid' => 'CA-incoming-1',
            'From' => '+34600000000',
            'To' => '+34910000000',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $response->assertSee('<Dial answerOnBridge="true" timeout="18"', false);
        $response->assertSee('<Identity>clinic-'.$clinic->id.'</Identity>', false);
        $response->assertSee('callerPhone', false);

        $this->assertDatabaseHas('call_logs', [
            'clinic_id' => $clinic->id,
            'twilio_call_sid' => 'CA-incoming-1',
            'status' => 'ringing',
        ]);
        $metadata = json_decode((string) DB::table('call_logs')->where('twilio_call_sid', 'CA-incoming-1')->value('metadata'), true);
        $this->assertSame('pending', $metadata['handled_by']);
    }

    public function test_nora_takes_over_when_the_browser_does_not_answer(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Salón Aurora']);
        DB::table('call_logs')->insert([
            'clinic_id' => $clinic->id,
            'twilio_call_sid' => 'CA-fallback-1',
            'from_phone' => '+34600000000',
            'status' => 'ringing',
            'transcript' => 'Hola, soy Nora y ya estoy atendiendo.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/twilio/voice/incoming/fallback', [
            'CallSid' => 'CA-fallback-1',
            'DialCallStatus' => 'no-answer',
        ]);

        $response->assertOk()->assertSee('Hola, soy Nora y ya estoy atendiendo.', false);
        $this->assertDatabaseHas('call_logs', [
            'twilio_call_sid' => 'CA-fallback-1',
            'status' => 'in-progress',
        ]);
        $metadata = json_decode((string) DB::table('call_logs')->where('twilio_call_sid', 'CA-fallback-1')->value('metadata'), true);
        $this->assertSame('nora', $metadata['handled_by']);
        $this->assertSame('Nora', $metadata['handled_by_name']);
    }

    public function test_answered_browser_call_records_the_clinic_name(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Salón Aurora']);
        DB::table('call_logs')->insert([
            'clinic_id' => $clinic->id,
            'twilio_call_sid' => 'CA-browser-answered',
            'from_phone' => '+34600000000',
            'status' => 'ringing',
            'metadata' => json_encode(['handled_by' => 'pending']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/twilio/voice/browser/status?parent_call_sid=CA-browser-answered', [
            'CallStatus' => 'answered',
        ])->assertOk();

        $log = DB::table('call_logs')->where('twilio_call_sid', 'CA-browser-answered')->first();
        $metadata = json_decode((string) $log->metadata, true);
        $this->assertSame('answered', $log->status);
        $this->assertSame('salon', $metadata['handled_by']);
        $this->assertSame('Salón Aurora', $metadata['handled_by_name']);
    }

    public function test_authenticated_console_receives_a_scoped_voice_token(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create(['name' => 'Salón Aurora']);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/twilio/voice/browser/token');

        $response->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('identity', 'clinic-'.$clinic->id);

        $token = $response->json('token');
        $segments = explode('.', $token);
        $this->assertCount(3, $segments);
        $payload = json_decode(base64_decode(strtr($segments[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('clinic-'.$clinic->id, $payload['grants']['identity']);
        $this->assertTrue($payload['grants']['voice']['incoming']['allow']);
        $this->assertSame(config('services.twilio.twiml_app_sid'), $payload['grants']['voice']['outgoing']['application_sid']);
    }
}
