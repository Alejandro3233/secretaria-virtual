<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTextToSpeechService
{
    public const TWILIO_VOICE_ID = 'twilio-basic';

    public const LANGUAGES = [
        'es' => 'Español',
    ];

    private const DISPLAY_VOICE_IDS = [
        'twilio-google-es-us-neural2-a',
        'twilio-polly-mia',
        'twilio-google-es-us-neural2-b',
        'twilio-google-es-us-chirp3-charon',
    ];

    public const TWILIO_VOICES = [
        self::TWILIO_VOICE_ID => [
            'name' => 'Secretaria basica',
            'description' => 'Voz basica gratis de Twilio para llamadas sin costo adicional de TTS.',
            'provider' => 'twilio',
            'badge' => 'Gratis',
            'voice' => 'woman',
            'language' => 'es-ES',
            'nora_language' => 'es',
            'gender' => 'Femenina',
        ],
        'twilio-google-es-us-neural2-a' => [
            'name' => 'Google Neural2 A — Español de EE. UU.',
            'description' => 'Voz femenina natural y equilibrada. Una de las opciones habituales para atención telefónica.',
            'provider' => 'twilio',
            'badge' => 'Neural · desde $0.0032/100 caracteres',
            'voice' => 'Google.es-US-Neural2-A',
            'language' => 'es-US',
            'nora_language' => 'es',
            'gender' => 'Femenina',
        ],
        'twilio-google-es-us-neural2-b' => [
            'name' => 'Google Neural2 B — Español de EE. UU.',
            'description' => 'Voz masculina natural y equilibrada para atención telefónica.',
            'provider' => 'twilio',
            'badge' => 'Neural · desde $0.0032/100 caracteres',
            'voice' => 'Google.es-US-Neural2-B',
            'language' => 'es-US',
            'nora_language' => 'es',
            'gender' => 'Masculina',
        ],
        'twilio-google-es-us-chirp3-aoede' => [
            'name' => 'Google Chirp3-HD Aoede — Español de EE. UU.',
            'description' => 'Voz femenina generativa, más expresiva y con mayor naturalidad conversacional.',
            'provider' => 'twilio',
            'badge' => 'Generativa · desde $0.013/100 caracteres',
            'voice' => 'Google.es-US-Chirp3-HD-Aoede',
            'language' => 'es-US',
            'nora_language' => 'es',
            'gender' => 'Femenina',
        ],
        'twilio-google-es-us-chirp3-charon' => [
            'name' => 'Google Chirp3-HD Charon — Español de EE. UU.',
            'description' => 'Voz masculina generativa, expresiva y con gran naturalidad conversacional.',
            'provider' => 'twilio',
            'badge' => 'Generativa · desde $0.013/100 caracteres',
            'voice' => 'Google.es-US-Chirp3-HD-Charon',
            'language' => 'es-US',
            'nora_language' => 'es',
            'gender' => 'Masculina',
        ],
        'twilio-polly-lupe' => [
            'name' => 'Amazon Polly Lupe — Español de EE. UU.',
            'description' => 'Voz femenina clara, cálida y pensada para español latino en Estados Unidos.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Lupe',
            'language' => 'es-US',
            'nora_language' => 'es',
            'gender' => 'Femenina',
        ],
        'twilio-polly-mia' => [
            'name' => 'Amazon Polly Mia — Español de México',
            'description' => 'Voz femenina natural, apropiada para clientes de México y gran parte de Latinoamérica.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Mia',
            'language' => 'es-MX',
            'nora_language' => 'es',
            'gender' => 'Femenina',
        ],
        'twilio-polly-joanna' => [
            'name' => 'Amazon Polly Joanna — English (US)',
            'description' => 'Clear and natural female voice for customer service in English.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Joanna',
            'language' => 'en-US',
            'nora_language' => 'en',
            'gender' => 'Femenina',
        ],
        'twilio-polly-matthew' => [
            'name' => 'Amazon Polly Matthew — English (US)',
            'description' => 'Natural male voice for customer service in English.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Matthew',
            'language' => 'en-US',
            'nora_language' => 'en',
            'gender' => 'Masculina',
        ],
        'twilio-polly-lea' => [
            'name' => 'Amazon Polly Léa — Français',
            'description' => 'Voix féminine claire et naturelle pour le service client en français.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Lea',
            'language' => 'fr-FR',
            'nora_language' => 'fr',
            'gender' => 'Femenina',
        ],
        'twilio-polly-mathieu' => [
            'name' => 'Amazon Polly Mathieu — Français',
            'description' => 'Voix masculine naturelle pour le service client en français.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Mathieu',
            'language' => 'fr-FR',
            'nora_language' => 'fr',
            'gender' => 'Masculina',
        ],
        'twilio-polly-camila' => [
            'name' => 'Amazon Polly Camila — Português (Brasil)',
            'description' => 'Voz feminina natural para atendimento ao cliente em português.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Camila',
            'language' => 'pt-BR',
            'nora_language' => 'pt',
            'gender' => 'Femenina',
        ],
        'twilio-polly-ricardo' => [
            'name' => 'Amazon Polly Ricardo — Português (Brasil)',
            'description' => 'Voz masculina natural para atendimento ao cliente em português.',
            'provider' => 'twilio',
            'badge' => 'Amazon Polly',
            'voice' => 'Polly.Ricardo',
            'language' => 'pt-BR',
            'nora_language' => 'pt',
            'gender' => 'Masculina',
        ],
    ];

    public const VOICES = [];

    public function configured(): bool
    {
        return (bool) ($this->credentialsPath() || config('google.tts.credentials_json'));
    }

    public function synthesize(string $text, ?string $voice = null): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('El texto para generar voz esta vacio.');
        }

        if ($this->isTwilioVoice($voice)) {
            throw new RuntimeException('La voz de Twilio se reproduce dentro de la llamada y no genera audio MP3.');
        }

        $response = Http::withToken($this->accessToken())
            ->post('https://texttospeech.googleapis.com/v1/text:synthesize', [
                'input' => [
                    'text' => $text,
                ],
                'voice' => [
                    'languageCode' => config('google.tts.language_code'),
                    'name' => $this->voiceName($voice),
                ],
                'audioConfig' => [
                    'audioEncoding' => config('google.tts.audio_encoding'),
                    'speakingRate' => config('google.tts.speaking_rate'),
                    'pitch' => config('google.tts.pitch'),
                ],
            ])
            ->throw();

        $audio = $response->json('audioContent');

        if (! $audio) {
            throw new RuntimeException('Google Text-to-Speech no devolvio audio.');
        }

        return base64_decode($audio, true) ?: throw new RuntimeException('El audio de Google Text-to-Speech no se pudo decodificar.');
    }

    public function voiceOptions(): array
    {
        return array_intersect_key(
            self::TWILIO_VOICES,
            array_flip(self::DISPLAY_VOICE_IDS),
        );
    }

    public function validVoice(?string $voice): bool
    {
        return is_string($voice) && array_key_exists($voice, $this->voiceOptions());
    }

    public function voicesForLanguage(string $language): array
    {
        $language = array_key_exists($language, self::LANGUAGES) ? $language : 'es';

        return array_filter(
            $this->voiceOptions(),
            fn (array $voice): bool => ($voice['nora_language'] ?? 'es') === $language,
        );
    }

    public function defaultVoiceForLanguage(string $language): string
    {
        return 'twilio-google-es-us-neural2-a';
    }

    public function isTwilioVoice(?string $voice): bool
    {
        return is_string($voice) && array_key_exists($voice, self::TWILIO_VOICES);
    }

    public function twilioSayAttributes(?string $voice, ?string $language = null): array
    {
        $voiceMatchesLanguage = is_string($voice)
            && array_key_exists($voice, self::TWILIO_VOICES)
            && ($language === null || (self::TWILIO_VOICES[$voice]['nora_language'] ?? 'es') === $language);
        $fallback = $this->defaultVoiceForLanguage('es');
        $option = $voiceMatchesLanguage
            ? self::TWILIO_VOICES[$voice]
            : self::TWILIO_VOICES[$fallback];

        return [
            'voice' => $option['voice'],
            'language' => $option['language'],
        ];
    }

    private function voiceName(?string $voice): string
    {
        if (is_string($voice) && array_key_exists($voice, self::VOICES)) {
            return $voice;
        }

        return (string) config('google.tts.voice');
    }

    private function accessToken(): string
    {
        $client = new GoogleClient();
        $client->setAuthConfig($this->authConfig());
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');

        $token = $client->fetchAccessTokenWithAssertion();

        if (isset($token['error'])) {
            throw new RuntimeException($token['error_description'] ?? $token['error']);
        }

        if (empty($token['access_token'])) {
            throw new RuntimeException('Google no devolvio un access token valido para Text-to-Speech.');
        }

        return $token['access_token'];
    }

    private function authConfig(): string|array
    {
        $path = $this->credentialsPath();

        if ($path) {
            return $path;
        }

        $json = config('google.tts.credentials_json');

        if ($json) {
            $decoded = json_decode((string) $json, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Configura GOOGLE_TTS_CREDENTIALS con la ruta del JSON de cuenta de servicio.');
    }

    private function credentialsPath(): ?string
    {
        $path = config('google.tts.credentials_path');

        if (! $path || ! is_file($path)) {
            return null;
        }

        return $path;
    }
}
