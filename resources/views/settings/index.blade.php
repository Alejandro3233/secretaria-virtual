@extends('layouts.app')

@section('title', 'Ajustes - Secretaria Virtual')
@section('page_title', 'Configuracion del negocio')
@section('page_subtitle', 'Organiza integraciones, reservas, facturacion y preferencias del salon.')

@section('content')
    @php($clinic = auth()->user()->primaryClinic())
    @php($googleTtsConfigured = (bool) ((config('google.tts.credentials_path') && is_file(config('google.tts.credentials_path'))) || config('google.tts.credentials_json')))
    @php($voiceOptions = app(\App\Services\GoogleTextToSpeechService::class)->voiceOptions())
    @php($activeVoice = $clinic?->google_tts_voice ?: \App\Services\GoogleTextToSpeechService::TWILIO_VOICE_ID)
    @php($voiceConfigured = $activeVoice === \App\Services\GoogleTextToSpeechService::TWILIO_VOICE_ID || $googleTtsConfigured)
    @php($twilioCountries = app(\App\Services\TwilioPhoneNumberService::class)->supportedCountries())
    @php($businessStatus = $clinic ? 'Activo' : 'Pendiente')
    @php($billingStatus = $clinic?->plan ? $clinic->plan->name : 'Sin plan')
    @php($timezone = $clinic?->localTimezone() ?: config('app.timezone'))
    @php($notificationAppointments = $clinic
        ? \App\Models\Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->where('starts_at', '>=', now($timezone)->startOfDay()->timezone(config('app.timezone')))
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('starts_at')
            ->limit(18)
            ->get()
            ->each(function ($appointment) use ($timezone) {
                $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
                $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
            })
        : collect())
    @php($callReminderCount = $notificationAppointments->where('reminder_call_enabled', true)->count())
    @php($smsReminderCount = $notificationAppointments->where('reminder_sms_enabled', true)->count())
    @php($notificationStatus = ($callReminderCount + $smsReminderCount) > 0 ? 'Activas' : 'Sin activar')
    @php($notificationPreferences = $clinic?->notificationPreferences() ?? \App\Models\Clinic::DEFAULT_NOTIFICATION_PREFERENCES)
    @php($activeNotificationRules = collect($notificationPreferences)->filter()->count())
    @php($smsNotificationRules = collect([
        $notificationPreferences['appointment_created_sms'] ?? false,
        $notificationPreferences['appointment_updated_sms'] ?? false,
        $notificationPreferences['appointment_reminder_sms'] ?? false,
        $notificationPreferences['appointment_reschedule_link_sms'] ?? false,
    ])->filter()->count())
    @php($callNotificationsActive = (bool) ($notificationPreferences['appointment_reminder_call'] ?? false))

    <style>
        .settings-shell {
            display: grid;
            gap: 18px;
        }

        .settings-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 290px);
            gap: 16px;
            align-items: center;
        }

        .settings-head h2 {
            font-size: 20px;
        }

        .settings-search {
            min-height: 46px;
            display: grid;
            grid-template-columns: 20px 1fr;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0 14px;
            background: white;
            box-shadow: 0 1px 2px rgba(24, 18, 22, .04);
        }

        .settings-search:focus-within {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(192, 38, 90, .1);
        }

        .settings-search input {
            border: 0;
            min-height: 42px;
            padding: 0;
            outline: 0;
            font-weight: 800;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(260px, 1fr));
            gap: 18px;
        }

        .settings-card {
            min-height: 112px;
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: start;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
            background: white;
            box-shadow: 0 1px 4px rgba(24, 18, 22, .06);
            color: inherit;
            cursor: pointer;
            font: inherit;
            text-align: left;
            width: 100%;
        }

        .settings-card:hover,
        .settings-card.active {
            border-color: #d7c4ce;
            box-shadow: 0 10px 24px rgba(24, 18, 22, .08);
        }

        .settings-card.active {
            outline: 2px solid rgba(192, 38, 90, .12);
        }

        .settings-card-icon {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            color: var(--brand);
        }

        .settings-card b,
        .settings-card span {
            display: block;
        }

        .settings-card b {
            margin-top: 3px;
            font-size: 16px;
        }

        .settings-card span {
            margin-top: 8px;
            color: var(--muted);
            line-height: 1.5;
        }

        .settings-arrow {
            color: #a3919b;
            font-size: 20px;
            line-height: 1;
        }

        .settings-panel {
            display: none;
        }

        .settings-shell.is-detail .settings-panel.active {
            display: block;
        }

        .settings-panel .card + .card {
            margin-top: 18px;
        }

        .settings-empty {
            display: none;
            border: 1px dashed var(--line);
            border-radius: 8px;
            padding: 22px;
            background: white;
            color: var(--muted);
            font-weight: 800;
        }

        .settings-empty.active {
            display: block;
        }

        .settings-detail-head {
            display: none;
            align-items: center;
            gap: 10px;
            min-height: 42px;
        }

        .settings-back {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
        }

        .settings-back:hover {
            background: #f3eef2;
        }

        .settings-detail-head h2 {
            margin: 0;
            font-size: 20px;
        }

        .settings-shell.is-detail .settings-head,
        .settings-shell.is-detail .settings-grid,
        .settings-shell.is-detail .settings-empty {
            display: none;
        }

        .settings-shell.is-detail .settings-detail-head {
            display: flex;
        }

        .settings-link-list {
            display: grid;
            gap: 10px;
        }

        .settings-link-row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            border: 1px solid #f2eaf0;
            border-radius: 8px;
            padding: 12px;
            background: #fffafd;
        }

        .settings-link-row b,
        .settings-link-row span {
            display: block;
        }

        .settings-link-row span {
            margin-top: 4px;
            color: var(--muted);
        }

        .notification-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .notification-stat {
            border: 1px solid #f2eaf0;
            border-radius: 8px;
            padding: 12px;
            background: #fffafd;
        }

        .notification-stat b,
        .notification-stat span {
            display: block;
        }

        .notification-stat b {
            font-size: 22px;
        }

        .notification-stat span {
            margin-top: 4px;
            color: var(--muted);
            font-weight: 800;
        }

        .notification-appointment-list {
            display: grid;
            gap: 10px;
        }

        .notification-rule-list {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .notification-rule {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            border: 1px solid #f2eaf0;
            border-radius: 8px;
            padding: 12px;
            background: #fffafd;
        }

        .notification-rule b,
        .notification-rule span {
            display: block;
        }

        .notification-rule span {
            margin-top: 4px;
            color: var(--muted);
        }

        .notification-appointment {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            border: 1px solid #f2eaf0;
            border-radius: 8px;
            padding: 12px;
            background: white;
        }

        .notification-appointment b,
        .notification-appointment span {
            display: block;
        }

        .notification-appointment span {
            margin-top: 4px;
            color: var(--muted);
        }

        .reminder-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin: 0;
        }

        .reminder-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            color: #475569;
            font-size: 13px;
            font-weight: 900;
            white-space: nowrap;
        }

        .reminder-toggle input {
            width: 16px;
            height: 16px;
            min-height: 16px;
            padding: 0;
            accent-color: var(--brand);
        }

        .reminder-toggle input:disabled + span {
            color: #94a3b8;
        }

        @media (max-width: 860px) {
            .settings-head,
            .settings-grid,
            .notification-summary,
            .notification-rule,
            .notification-appointment {
                grid-template-columns: 1fr;
            }

            .settings-card {
                grid-template-columns: 32px minmax(0, 1fr);
            }

            .settings-arrow {
                display: none;
            }

            .settings-link-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .reminder-controls {
                justify-content: flex-start;
            }
        }
    </style>

    @foreach ([
        'google_calendar_status',
        'google_tts_status',
        'twilio_number_status',
        'appointment_status',
        'settings_status',
    ] as $statusKey)
        @if (session($statusKey))
            <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
                {{ session($statusKey) }}
            </div>
        @endif
    @endforeach

    @foreach ([
        'google_calendar_error',
        'google_tts_error',
        'twilio_number_error',
        'appointment_error',
    ] as $errorKey)
        @if (session($errorKey))
            <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
                {{ session($errorKey) }}
            </div>
        @endif
    @endforeach

    <div class="settings-shell">
        <div class="settings-head">
            <div>
                <h2>Configuracion del negocio</h2>
                <span class="subtitle">Accede a cada area desde una tarjeta.</span>
            </div>
            <label class="settings-search">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input id="settings-search" type="search" placeholder="Buscar" autocomplete="off">
            </label>
        </div>

        <section class="settings-grid" aria-label="Categorias de ajustes">
            <button class="settings-card active" type="button" data-settings-card="negocio" data-settings-search="informacion negocio salon perfil ubicacion numero twilio llamadas">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-8h6v8"/></svg></span>
                <span><b>Informacion del negocio</b><span>Datos del salon, numero asignado y estado operativo.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="notificaciones" data-settings-search="notificaciones recordatorios sms llamadas citas agenda clientes">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg></span>
                <span><b>Notificaciones</b><span>Activa recordatorios por SMS o llamada para cada cita.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="servicios" data-settings-search="servicios personal estilistas empleados catalogo">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><circle cx="8" cy="7" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="17" r="1"/></svg></span>
                <span><b>Configuracion de servicios</b><span>Catalogo, duraciones, precios y profesionales disponibles.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="avanzadas" data-settings-search="opciones avanzadas voz secretaria recordatorios sms llamadas twilio">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 16 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.38.52.7.9.9.34.18.72.28 1.1.28h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-2 1z"/></svg></span>
                <span><b>Opciones avanzadas</b><span>Voz, SMS, llamadas y preferencias de automatizacion.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="facturacion" data-settings-search="suscripcion facturacion pagos ventas stripe plan">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2z"/><path d="M9 7h6"/><path d="M9 11h6"/><path d="M9 15h4"/></svg></span>
                <span><b>Suscripcion y facturacion</b><span>Plan actual, facturas y pagos de la plataforma.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="personal" data-settings-search="configuracion personal cuenta usuario idioma perfil">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21a8 8 0 1 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></span>
                <span><b>Configuracion personal</b><span>Cuenta del usuario, accesos y preferencias de trabajo.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>
        </section>

        <div class="settings-empty" id="settings-empty">No hay categorias que coincidan con la busqueda.</div>

        <div class="settings-detail-head" aria-label="Detalle de ajuste">
            <button class="settings-back" type="button" aria-label="Volver al menu de ajustes" title="Volver">
                <svg class="icon" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            </button>
            <h2 id="settings-detail-title">Ajustes</h2>
        </div>

        <section class="settings-panel active" data-settings-panel="negocio" data-settings-title="Informacion del negocio">
            <article class="card integration-status-bar">
                <div class="section-title">
                    <div>
                        <h2>Informacion del negocio</h2>
                        <span class="subtitle">Resumen principal del salon y sus integraciones.</span>
                    </div>
                    <span class="status {{ $clinic ? 'ok' : 'wait' }}">{{ $businessStatus }}</span>
                </div>
                <div class="grid-3">
                    <div class="item" style="border-bottom:0;padding:0;">
                        <div>
                            <b>Salon</b>
                            <span>{{ $clinic?->name ?? 'Pendiente de configurar' }}</span>
                        </div>
                    </div>
                    <div class="item" style="border-bottom:0;padding:0;">
                        <div>
                            <b>Pais</b>
                            <span>{{ $twilioCountries[$clinic?->country_code ?? 'US'] ?? ($clinic?->country_code ?? 'US') }}</span>
                        </div>
                    </div>
                    <div class="item" style="border-bottom:0;padding:0;">
                        <div>
                            <b>Estado de plan</b>
                            <span>{{ ucfirst($clinic?->subscription_status ?? 'pendiente') }}</span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card" id="numero-asignado">
                <div class="section-title">
                    <div>
                        <h2>Numero asignado</h2>
                        <span class="subtitle">Numero dedicado para desviar llamadas a la secretaria virtual.</span>
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
            </article>
        </section>

        <section class="settings-panel" data-settings-panel="notificaciones" data-settings-title="Notificaciones">
            <article class="card" id="notificaciones">
                <div class="section-title">
                    <div>
                        <h2>Notificaciones</h2>
                        <span class="subtitle">Recordatorios automaticos por llamada y SMS segun cada cita agendada.</span>
                    </div>
                </div>

                <div class="notification-summary">
                    <div class="notification-stat">
                        <b>{{ $activeNotificationRules }} de {{ count(\App\Models\Clinic::DEFAULT_NOTIFICATION_PREFERENCES) }}</b>
                        <span>Reglas activas</span>
                    </div>
                    <div class="notification-stat">
                        <b>{{ $smsNotificationRules }}</b>
                        <span>Reglas SMS activas</span>
                    </div>
                    <div class="notification-stat">
                        <b>{{ $callNotificationsActive ? 'Activa' : 'Inactiva' }}</b>
                        <span>Llamada de recordatorio</span>
                    </div>
                </div>

                <form method="POST" action="/ajustes/notificaciones" class="notification-rule-list">
                    @csrf
                    @foreach ([
                        'appointment_created_sms' => ['SMS al crear cita', 'Confirmacion por SMS cuando una cita se agenda por primera vez.'],
                        'appointment_created_email' => ['Email al crear cita', 'Confirmacion por correo cuando una cita se agenda por primera vez.'],
                        'appointment_updated_sms' => ['SMS al mover o editar cita', 'Aviso por SMS cuando cambia la hora, servicio, estado o profesional.'],
                        'appointment_updated_email' => ['Email al mover o editar cita', 'Aviso por correo cuando cambia la hora, servicio, estado o profesional.'],
                        'appointment_reminder_sms' => ['SMS de recordatorio', 'Mensaje automatico antes de la cita si el recordatorio esta activo en esa cita.'],
                        'appointment_reminder_call' => ['Llamada de recordatorio', 'Llamada automatica antes de la cita si el recordatorio esta activo en esa cita.'],
                        'appointment_reschedule_link_sms' => ['SMS para reagendar', 'Enlace por SMS cuando el cliente marca la opcion de reagendar en una llamada.'],
                    ] as $preferenceKey => [$preferenceTitle, $preferenceDescription])
                        <div class="notification-rule">
                            <div>
                                <b>{{ $preferenceTitle }}</b>
                                <span>{{ $preferenceDescription }}</span>
                            </div>
                            <label class="reminder-toggle">
                                <input type="hidden" name="{{ $preferenceKey }}" value="0">
                                <input type="checkbox" name="{{ $preferenceKey }}" value="1" @checked($notificationPreferences[$preferenceKey] ?? false)>
                                <span>Activo</span>
                            </label>
                        </div>
                    @endforeach
                    <div class="actions" style="justify-content:flex-end;">
                        <button class="btn primary" type="submit">Guardar notificaciones</button>
                    </div>
                </form>

            </article>
        </section>

        <section class="settings-panel" data-settings-panel="servicios" data-settings-title="Configuracion de servicios">
            <article class="card">
                <div class="section-title">
                    <div>
                        <h2>Configuracion de servicios</h2>
                        <span class="subtitle">Catalogo, duraciones, precios y equipo.</span>
                    </div>
                    <a class="btn primary" href="/personal/servicios">Abrir servicios</a>
                </div>
                <div class="settings-link-list">
                    <div class="settings-link-row">
                        <div>
                            <b>Servicios del salon</b>
                            <span>Crea servicios, precios, duraciones y estados activos.</span>
                        </div>
                        <a class="btn" href="/personal/servicios">Gestionar</a>
                    </div>
                    <div class="settings-link-row">
                        <div>
                            <b>Personal</b>
                            <span>Horarios, estilistas y asignacion de servicios.</span>
                        </div>
                        <a class="btn" href="/personal">Gestionar</a>
                    </div>
                </div>
            </article>
        </section>

        <section class="settings-panel" data-settings-panel="avanzadas" data-settings-title="Opciones avanzadas">
            <article class="card integration-status-bar" id="voz-secretaria">
                <div class="section-title">
                    <div>
                        <h2>Opciones avanzadas</h2>
                        <span class="subtitle">Voz, llamadas y mensajes de la secretaria virtual.</span>
                    </div>
                    <span class="status integration-status {{ $voiceConfigured ? 'ok' : 'wait' }}">{{ $voiceConfigured ? 'Configurada' : 'Pendiente' }}</span>
                </div>

                <div style="display:grid;gap:12px;">
                    @foreach ($voiceOptions as $voiceId => $voice)
                        @php($isActiveVoice = $activeVoice === $voiceId)
                        @php($isTwilioVoice = ($voice['provider'] ?? null) === 'twilio')
                        @php($canPreview = $isTwilioVoice || $googleTtsConfigured)
                        <div class="item" style="align-items:center;">
                            <div>
                                <b>{{ $voice['name'] }}</b>
                                <span>{{ $voice['description'] }} @if (! empty($voice['badge'])) - {{ $voice['badge'] }} @endif</span>
                            </div>
                            <div class="actions" style="margin:0;">
                                <span class="status integration-status {{ $isActiveVoice ? 'ok' : 'wait' }}">{{ $isActiveVoice ? 'Activa' : 'Disponible' }}</span>
                                @if ($canPreview)
                                    @if ($isTwilioVoice)
                                        <button class="btn js-twilio-voice-preview" type="button" data-preview-text="Hola, soy la secretaria virtual del salon. Puedo ayudarte a confirmar, cambiar o reservar una cita.">Escuchar prueba</button>
                                    @else
                                        <button class="btn js-google-voice-preview" type="button" data-preview-url="{{ route('secretary-voice.preview', ['voice' => $voiceId]) }}">Escuchar prueba</button>
                                    @endif
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
            </article>
        </section>

        <section class="settings-panel" data-settings-panel="facturacion" data-settings-title="Suscripcion y facturacion">
            <article class="card">
                <div class="section-title">
                    <div>
                        <h2>Suscripcion y facturacion</h2>
                        <span class="subtitle">Plan, facturas y pagos de Secretaria Virtual.</span>
                    </div>
                    <span class="status {{ $clinic?->plan ? 'ok' : 'wait' }}">{{ $billingStatus }}</span>
                </div>
                <div class="settings-link-row">
                    <div>
                        <b>Centro de facturacion</b>
                        <span>{{ $clinic?->plan ? $clinic->plan->sidebarDescription() : 'Elige un plan para activar la facturacion.' }}</span>
                    </div>
                    <a class="btn primary" href="/facturacion">Abrir facturacion</a>
                </div>
            </article>
        </section>

        <section class="settings-panel" data-settings-panel="personal" data-settings-title="Configuracion personal">
            <article class="card">
                <div class="section-title">
                    <div>
                        <h2>Configuracion personal</h2>
                        <span class="subtitle">Cuenta del usuario actual.</span>
                    </div>
                    <span class="status ok">Activa</span>
                </div>
                <div class="grid-2">
                    <div>
                        <label>Usuario</label>
                        <input value="{{ auth()->user()?->name }}" readonly>
                    </div>
                    <div>
                        <label>Email</label>
                        <input value="{{ auth()->user()?->email }}" readonly>
                    </div>
                </div>
            </article>
        </section>
    </div>

    <script>
        const settingsCards = Array.from(document.querySelectorAll('[data-settings-card]'));
        const settingsPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));
        const settingsSearch = document.getElementById('settings-search');
        const settingsEmpty = document.getElementById('settings-empty');
        const settingsShell = document.querySelector('.settings-shell');
        const settingsBack = document.querySelector('.settings-back');
        const settingsDetailTitle = document.getElementById('settings-detail-title');

        function showSettingsPanel(panelName) {
            settingsCards.forEach((card) => card.classList.toggle('active', card.dataset.settingsCard === panelName));
            settingsPanels.forEach((panel) => {
                const isActive = panel.dataset.settingsPanel === panelName;
                panel.classList.toggle('active', isActive);

                if (isActive && settingsDetailTitle) {
                    settingsDetailTitle.textContent = panel.dataset.settingsTitle || 'Ajustes';
                }
            });
            settingsShell?.classList.add('is-detail');
        }

        settingsCards.forEach((card) => {
            card.addEventListener('click', () => {
                showSettingsPanel(card.dataset.settingsCard);
                history.replaceState(null, '', `#${card.dataset.settingsCard}`);
            });
        });

        settingsBack?.addEventListener('click', () => {
            settingsShell?.classList.remove('is-detail');
            settingsCards.forEach((card) => card.classList.remove('active'));
            history.replaceState(null, '', window.location.pathname);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        settingsSearch?.addEventListener('input', () => {
            const query = settingsSearch.value.trim().toLowerCase();
            let visibleCount = 0;

            settingsCards.forEach((card) => {
                const haystack = `${card.textContent} ${card.dataset.settingsSearch || ''}`.toLowerCase();
                const visible = query === '' || haystack.includes(query);
                card.style.display = visible ? '' : 'none';
                visibleCount += visible ? 1 : 0;
            });

            settingsEmpty?.classList.toggle('active', visibleCount === 0);
        });

        const initialSettingsTarget = window.location.hash.replace('#', '');
        const initialPanelFromCard = settingsCards.find((card) => card.dataset.settingsCard === initialSettingsTarget)?.dataset.settingsCard;
        const initialPanelFromElement = initialSettingsTarget
            ? document.getElementById(initialSettingsTarget)?.closest('[data-settings-panel]')?.dataset.settingsPanel
            : null;
        const initialSettingsPanel = initialPanelFromCard || initialPanelFromElement;

        if (initialSettingsPanel) {
            showSettingsPanel(initialSettingsPanel);

            if (initialPanelFromElement) {
                document.getElementById(initialSettingsTarget)?.scrollIntoView({ block: 'start' });
            }
        }

        let secretaryVoiceAudio = null;

        function stopSecretaryVoicePreview() {
            window.speechSynthesis?.cancel();

            if (secretaryVoiceAudio) {
                secretaryVoiceAudio.pause();
                secretaryVoiceAudio.currentTime = 0;
            }
        }

        document.querySelectorAll('.js-twilio-voice-preview').forEach((button) => {
            button.addEventListener('click', () => {
                if (!('speechSynthesis' in window)) {
                    alert('Tu navegador no permite escuchar esta prueba.');
                    return;
                }

                stopSecretaryVoicePreview();

                const utterance = new SpeechSynthesisUtterance(button.dataset.previewText || '');
                utterance.lang = 'es-US';
                utterance.rate = 1;
                utterance.pitch = 1;

                window.speechSynthesis.speak(utterance);
            });
        });

        document.querySelectorAll('.js-google-voice-preview').forEach((button) => {
            button.addEventListener('click', async () => {
                const originalText = button.textContent;

                try {
                    stopSecretaryVoicePreview();
                    button.disabled = true;
                    button.textContent = 'Cargando...';

                    const response = await fetch(button.dataset.previewUrl, {
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo generar la prueba.');
                    }

                    const audioBlob = await response.blob();
                    const audioUrl = URL.createObjectURL(audioBlob);

                    secretaryVoiceAudio = new Audio(audioUrl);
                    secretaryVoiceAudio.addEventListener('ended', () => URL.revokeObjectURL(audioUrl), { once: true });
                    secretaryVoiceAudio.play();
                } catch (error) {
                    alert(error.message || 'No se pudo escuchar esta prueba.');
                } finally {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        });
    </script>
@endsection
