@extends('layouts.app')

@section('title', 'Ajustes - Secretaria Virtual')
@section('page_title', 'Ajustes')
@section('page_subtitle', 'Configura el numero asignado, Google Calendar y la voz de la secretaria.')

@section('content')
    @php($clinic = auth()->user()->primaryClinic())
    @php($googleTtsConfigured = (bool) ((config('google.tts.credentials_path') && is_file(config('google.tts.credentials_path'))) || config('google.tts.credentials_json')))
    @php($voiceOptions = app(\App\Services\GoogleTextToSpeechService::class)->voiceOptions())
    @php($activeVoice = $clinic?->google_tts_voice ?: config('google.tts.voice'))
    @php($twilioCountries = app(\App\Services\TwilioPhoneNumberService::class)->supportedCountries())

    @if (session('google_calendar_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('google_calendar_status') }}
        </div>
    @endif

    @if (session('google_calendar_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('google_calendar_error') }}
        </div>
    @endif

    @if (session('google_tts_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('google_tts_status') }}
        </div>
    @endif

    @if (session('google_tts_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('google_tts_error') }}
        </div>
    @endif

    @if (session('twilio_number_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('twilio_number_status') }}
        </div>
    @endif

    @if (session('twilio_number_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('twilio_number_error') }}
        </div>
    @endif

    <section class="card integration-status-bar" style="margin-bottom:18px;">
        <div class="section-title">
            <h2>Estado de integraciones</h2>
        </div>
        <div class="grid-3">
            <div class="item" style="border-bottom:0;padding:0;">
                <div>
                    <b>Numero asignado</b>
                    <span>{{ $clinic?->twilio_phone_number ? $clinic->twilio_phone_number : 'Pendiente de asignacion.' }}</span>
                </div>
                <span class="status integration-status {{ $clinic?->twilio_phone_number ? 'ok' : 'wait' }}">{{ $clinic?->twilio_phone_number ? 'Conectado' : 'Pendiente' }}</span>
            </div>
            <div class="item" style="border-bottom:0;padding:0;">
                <div>
                    <b>Google Calendar</b>
                    <span>
                        @if ($clinic?->google_connected_at)
                            {{ $clinic->google_calendar_summary ?? 'Calendario conectado' }}. Ultima sincronizacion: {{ $clinic->google_last_synced_at?->diffForHumans() ?? 'pendiente' }}.
                        @else
                            Pendiente de conectar.
                        @endif
                    </span>
                </div>
                <span class="status integration-status {{ $clinic?->google_connected_at ? 'ok' : 'wait' }}">{{ $clinic?->google_connected_at ? 'Conectado' : 'Pendiente' }}</span>
            </div>
            <div class="item" style="border-bottom:0;padding:0;">
                <div>
                    <b>Voz secretaria</b>
                    <span>{{ $googleTtsConfigured ? 'Voz configurada para llamadas.' : 'Pendiente de configurar.' }}</span>
                </div>
                <span class="status integration-status {{ $googleTtsConfigured ? 'ok' : 'wait' }}">{{ $googleTtsConfigured ? 'Conectado' : 'Pendiente' }}</span>
            </div>
        </div>
    </section>

    <section class="card" id="numero-asignado">
        <div class="section-title">
            <div>
                <h2>Numero asignado</h2>
                <span class="subtitle">Numero dedicado para que el salon desvie sus llamadas a la secretaria virtual.</span>
            </div>
            <span class="status {{ $clinic?->twilio_phone_number ? 'ok' : 'wait' }}">{{ $clinic?->twilio_phone_number ? 'Asignado' : 'Pendiente' }}</span>
        </div>

        <div class="grid-3">
            <div>
                <label>Pais del salon</label>
                <input value="{{ $twilioCountries[$clinic?->country_code ?? 'US'] ?? ($clinic?->country_code ?? 'US') }}" readonly>
            </div>
            <div>
                <label>Numero asignado</label>
                <input value="{{ $clinic?->twilio_phone_number ?? 'Pendiente' }}" readonly>
            </div>
            <div>
                <label>Estado</label>
                <input value="{{ ucfirst($clinic?->twilio_number_status ?? 'pending') }}" readonly>
            </div>
        </div>

        @if ($clinic?->twilio_number_error && ! $clinic?->twilio_phone_number)
            <div style="margin-top:14px;color:#991b1b;font-weight:800;">
                {{ $clinic->twilio_number_error }}
            </div>
        @endif

        <div class="actions" style="margin-top:18px;">
            @if ($clinic?->twilio_phone_number)
                <span class="btn">Listo para desviar llamadas</span>
            @else
                <form method="POST" action="{{ route('twilio-number.assign') }}">
                    @csrf
                    <button class="btn primary" type="submit">Asignar numero automaticamente</button>
                </form>
            @endif
        </div>
    </section>

    <section class="card" id="google-calendar" style="margin-top:18px;">
        <div class="section-title">
            <div>
                <h2>Google Calendar</h2>
                <span class="subtitle">Agenda sincronizada con Google Calendar.</span>
            </div>
            @if ($clinic?->google_connected_at)
                <span class="status ok">Conectado</span>
            @else
                <span class="status wait">No conectado</span>
            @endif
        </div>

        <div class="grid-2">
            <div>
                <label>Correo sincronizado</label>
                <input value="{{ $clinic?->google_calendar_summary ?? 'No conectado' }}" readonly>
            </div>
            <div>
                <label>Ultima sincronizacion</label>
                <input value="{{ $clinic?->google_last_synced_at?->format('Y-m-d H:i') ?? 'Pendiente' }}" readonly>
            </div>
        </div>

        <div class="actions" style="margin-top:18px;">
            @if ($clinic?->google_connected_at)
                <form method="POST" action="/google-calendar/sync">
                    @csrf
                    <button class="btn primary" type="submit">Sincronizar ahora</button>
                </form>
                <form method="POST" action="/google-calendar/disconnect">
                    @csrf
                    <button class="btn" type="submit">Desconectar</button>
                </form>
            @else
                <a class="btn primary" href="/google-calendar/connect">Conectar Google Calendar</a>
            @endif
        </div>
    </section>

    <section class="card" id="voz-secretaria" style="margin-top:18px;">
        <div class="section-title">
            <div>
                <h2>Prueba las voces</h2>
                <span class="subtitle">Escucha las opciones y activa la voz que usara la secretaria en las llamadas.</span>
            </div>
            <span class="status {{ $googleTtsConfigured ? 'ok' : 'wait' }}">{{ $googleTtsConfigured ? 'Configurada' : 'Pendiente' }}</span>
        </div>

        <div style="display:grid;gap:12px;">
            @foreach ($voiceOptions as $voiceId => $voice)
                @php($isActiveVoice = $activeVoice === $voiceId)
                <div class="item" style="align-items:center;">
                    <div>
                        <b>{{ $voice['name'] }}</b>
                        <span>{{ $voice['description'] }}</span>
                    </div>
                    <div class="actions" style="margin:0;">
                        <span class="status {{ $isActiveVoice ? 'ok' : 'wait' }}">{{ $isActiveVoice ? 'Activa' : 'Disponible' }}</span>
                        @if ($googleTtsConfigured)
                            <a class="btn" href="{{ route('secretary-voice.preview', ['voice' => $voiceId]) }}" target="_blank">Escuchar prueba</a>
                            <form method="POST" action="{{ route('secretary-voice.activate') }}">
                                @csrf
                                <input type="hidden" name="voice" value="{{ $voiceId }}">
                                <button class="btn {{ $isActiveVoice ? '' : 'primary' }}" type="submit">{{ $isActiveVoice ? 'Activada' : 'Activar' }}</button>
                            </form>
                        @else
                            <span class="btn">Configura credenciales primero</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    </section>
@endsection
