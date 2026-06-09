<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTextToSpeechService
{
    public const TWILIO_VOICE_ID = 'twilio-basic';

    public const TWILIO_VOICE = [
        'name' => 'Secretaria estandar',
        'description' => 'Voz incluida para llamadas',
        'provider' => 'twilio',
        'badge' => 'Gratis',
    ];

    public const VOICES = [
        'es-US-Neural2-A' => [
            'name' => 'Sofia',
            'description' => 'Femenina natural',
            'provider' => 'google',
            'badge' => 'Premium',
        ],
        'es-US-Neural2-B' => [
            'name' => 'Mateo',
            'description' => 'Masculina natural',
            'provider' => 'google',
            'badge' => 'Premium',
        ],
        'es-US-Neural2-C' => [
            'name' => 'Carlos',
            'description' => 'Masculina alternativa',
            'provider' => 'google',
            'badge' => 'Premium',
        ],
        'es-US-Chirp3-HD-Aoede' => [
            'name' => 'Premium HD',
            'description' => 'Voz avanzada',
            'provider' => 'google',
            'badge' => 'Premium HD',
        ],
    ];

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
        return [
            self::TWILIO_VOICE_ID => self::TWILIO_VOICE,
            ...self::VOICES,
        ];
    }

    public function validVoice(?string $voice): bool
    {
        return is_string($voice) && array_key_exists($voice, $this->voiceOptions());
    }

    public function isTwilioVoice(?string $voice): bool
    {
        return $voice === self::TWILIO_VOICE_ID;
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
