<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\Services\GoogleTextToSpeechService;
use App\Services\NoraLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoraLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_two_female_and_two_male_spanish_voices_are_available(): void
    {
        $voices = app(GoogleTextToSpeechService::class)->voiceOptions();

        $this->assertCount(4, $voices);
        $this->assertCount(2, array_filter($voices, fn (array $voice) => $voice['gender'] === 'Femenina'));
        $this->assertCount(2, array_filter($voices, fn (array $voice) => $voice['gender'] === 'Masculina'));
        $this->assertSame(['es'], array_values(array_unique(array_column($voices, 'nora_language'))));
    }

    public function test_nora_remains_in_spanish_and_user_can_change_the_voice(): void
    {
        $user = User::factory()->create();
        $clinic = Clinic::query()->create([
            'name' => 'Salon Aurora',
            'google_tts_voice' => 'twilio-polly-joanna',
            'notification_preferences' => ['nora_language' => 'en'],
        ]);
        DB::table('clinic_users')->insert([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('es', app(NoraLanguageService::class)->language($clinic));

        $this->actingAs($user)
            ->post('/voz-secretaria/activar', ['voice' => 'twilio-google-es-us-neural2-b'])
            ->assertRedirect('/ajustes#avanzadas');

        $clinic->refresh();
        $this->assertSame('es', $clinic->notification_preferences['nora_language']);
        $this->assertSame('twilio-google-es-us-neural2-b', $clinic->google_tts_voice);
    }
}
