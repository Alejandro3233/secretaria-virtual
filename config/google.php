<?php

return [
    'calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL').'/google-calendar/callback'),
    ],

    'auth' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_AUTH_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'tts' => [
        'credentials_path' => env('GOOGLE_TTS_CREDENTIALS'),
        'credentials_json' => env('GOOGLE_TTS_CREDENTIALS_JSON'),
        'language_code' => env('GOOGLE_TTS_LANGUAGE_CODE', 'es-US'),
        'voice' => env('GOOGLE_TTS_VOICE', 'es-US-Neural2-A'),
        'audio_encoding' => env('GOOGLE_TTS_AUDIO_ENCODING', 'MP3'),
        'speaking_rate' => (float) env('GOOGLE_TTS_SPEAKING_RATE', 1.0),
        'pitch' => (float) env('GOOGLE_TTS_PITCH', 0.0),
    ],
];
