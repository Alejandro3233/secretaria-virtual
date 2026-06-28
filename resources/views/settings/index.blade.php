@extends('layouts.app')

@section('title', 'Ajustes - Secretary365')
@section('page_title', 'Configuracion del negocio')
@section('page_subtitle', 'Organiza integraciones, reservas, facturacion y preferencias del salon.')

@section('content')
    @php($currentUser = auth()->user())
    @php($clinic = $currentUser->primaryClinic())
    @php($googleTtsConfigured = (bool) ((config('google.tts.credentials_path') && is_file(config('google.tts.credentials_path'))) || config('google.tts.credentials_json')))
    @php($voiceService = app(\App\Services\GoogleTextToSpeechService::class))
    @php($voiceOptions = $voiceService->voiceOptions())
    @php($activeVoice = $voiceService->validVoice($clinic?->google_tts_voice) ? $clinic?->google_tts_voice : \App\Services\GoogleTextToSpeechService::TWILIO_VOICE_ID)
    @php($voiceConfigured = $voiceService->isTwilioVoice($activeVoice) || $googleTtsConfigured)
    @php($pendingContactChange = session('settings_contact_change'))
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
    @php($notificationBooleanKeys = [
        'appointment_created_sms',
        'appointment_created_email',
        'appointment_updated_sms',
        'appointment_updated_email',
        'appointment_reminder_sms',
        'appointment_reminder_call',
        'appointment_reschedule_link_sms',
    ])
    @php($activeNotificationRules = collect($notificationBooleanKeys)->filter(fn ($key) => (bool) ($notificationPreferences[$key] ?? false))->count())
    @php($reminderSmsHoursBefore = (int) ($notificationPreferences['appointment_reminder_sms_hours_before'] ?? 24))
    @php($reminderCallHoursBefore = (int) ($notificationPreferences['appointment_reminder_call_hours_before'] ?? 24))
    @php($smsNotificationRules = collect([
        $notificationPreferences['appointment_created_sms'] ?? false,
        $notificationPreferences['appointment_updated_sms'] ?? false,
        $notificationPreferences['appointment_reminder_sms'] ?? false,
        $notificationPreferences['appointment_reschedule_link_sms'] ?? false,
    ])->filter()->count())
    @php($callNotificationsActive = (bool) ($notificationPreferences['appointment_reminder_call'] ?? false))
    @php($callForwardingMode = $notificationPreferences['call_forwarding_mode'] ?? 'no_answer')
    @php($callForwardingSeconds = (int) ($notificationPreferences['call_forwarding_ring_seconds'] ?? 20))
    @php($callForwardingSent = ! empty($notificationPreferences['call_forwarding_instructions_sent_at']))
    @php($forwardingService = app(\App\Services\CallForwardingInstructionService::class))
    @php($callForwardingCountry = $forwardingService->countryForPhone($clinic?->phone, $clinic?->country_code))
    @php($callForwardingOperators = $forwardingService->operators($callForwardingCountry))
    @php($callForwardingOperator = $notificationPreferences['call_forwarding_operator'] ?? array_key_first($callForwardingOperators))
    @php($noraLanguage = 'es')
    @php($voiceOptions = $voiceService->voiceOptions())
    @php($activeVoice = array_key_exists((string) $activeVoice, $voiceOptions) ? $activeVoice : $voiceService->defaultVoiceForLanguage($noraLanguage))
    @php($googleCalendarMappings = $clinic?->googleCalendarMappings()->with('stylist')->orderByDesc('is_primary')->orderBy('google_calendar_name')->get() ?? collect())
    @php($googleStylists = $clinic?->stylists()->where('is_internal', false)->where('is_active', true)->orderBy('name')->get() ?? collect())
    @php($facilityResources = $clinic?->facilityResources()->orderBy('name')->get() ?? collect())
    @php($resourceServices = $clinic?->services()->with('facilityResource')->orderBy('name')->get() ?? collect())

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

        .forwarding-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .forwarding-option { position: relative; display: block; cursor: pointer; }
        .forwarding-option input { position: absolute; opacity: 0; pointer-events: none; }
        .forwarding-option-body { min-height: 112px; display: block; border: 1px solid var(--line); border-radius: 8px; padding: 15px; background: white; transition: .16s ease; }
        .forwarding-option-body b, .forwarding-option-body span { display: block; }
        .forwarding-option-body b { color: var(--ink); font-size: 14px; }
        .forwarding-option-body span { margin-top: 7px; color: var(--muted); font-size: 13px; line-height: 1.45; }
        .forwarding-option input:checked + .forwarding-option-body { border-color: var(--brand); background: #fff5f8; box-shadow: 0 0 0 3px rgba(192,38,90,.09); }
        .forwarding-option.recommended .forwarding-option-body::before { content: "Recomendado"; display: inline-block; margin-bottom: 9px; border-radius: 999px; padding: 3px 8px; color: #166534; background: #dcfce7; font-size: 10px; font-weight: 900; text-transform: uppercase; }
        .forwarding-guide { margin-top: 16px; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px; color: #1e3a8a; background: #eff6ff; font-size: 13px; line-height: 1.55; }
        .forwarding-guide b { display: block; margin-bottom: 5px; }
        .calendar-map-list { display: grid; gap: 10px; margin-top: 16px; }
        .calendar-map-row { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(220px, .9fr) auto; gap: 14px; align-items: center; border: 1px solid var(--line); border-radius: 8px; padding: 14px; background: white; }
        .calendar-map-name b, .calendar-map-name span { display: block; }
        .calendar-map-name span { margin-top: 4px; color: var(--muted); font-size: 12px; }
        .calendar-map-enabled { display: inline-flex; align-items: center; gap: 7px; margin: 0; font-weight: 800; white-space: nowrap; }
        .calendar-map-enabled input { width: 17px; height: 17px; min-height: 17px; padding: 0; accent-color: var(--brand); }
        .calendar-organization { margin-top: 18px; padding: 18px; border: 1px solid var(--line); border-radius: 10px; background: #fffafd; }
        .calendar-organization h3 { margin: 0; font-size: 16px; }
        .calendar-organization-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
        .calendar-organization-option { position: relative; display: flex; gap: 10px; align-items: flex-start; min-height: 92px; padding: 14px; border: 1px solid var(--line); border-radius: 8px; background: white; cursor: pointer; }
        .calendar-organization-option:has(input:checked) { border-color: var(--brand); box-shadow: 0 0 0 2px rgba(192,38,90,.08); }
        .calendar-organization-option input { width: 17px; height: 17px; min-height: 17px; margin-top: 2px; accent-color: var(--brand); }
        .calendar-organization-option b, .calendar-organization-option span { display: block; }
        .calendar-organization-option span { margin-top: 5px; color: var(--muted); font-size: 12px; line-height: 1.35; }
        .calendar-recommended { color: var(--brand) !important; font-size: 10px !important; font-weight: 900; text-transform: uppercase; }

        @media (max-width: 860px) {
            .settings-head,
            .settings-grid,
            .notification-summary,
            .notification-rule,
            .notification-appointment {
                grid-template-columns: 1fr;
            }

            .forwarding-options { grid-template-columns: 1fr; }
            .calendar-map-row { grid-template-columns: 1fr; }
            .calendar-organization-options { grid-template-columns: 1fr; }

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
        'settings_error',
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

            <button class="settings-card" type="button" data-settings-card="google-calendar" data-settings-search="google calendar calendarios sincronizacion especialistas empleados detectar asignar">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M9 15h6M12 12v6"/></svg></span>
                <span><b>Google Calendar</b><span>Detecta calendarios y asigna cada uno a su especialista.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="servicios" data-settings-search="servicios personal estilistas empleados catalogo">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><circle cx="8" cy="7" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="17" r="1"/></svg></span>
                <span><b>Configuracion de servicios</b><span>Catalogo, duraciones, precios y profesionales disponibles.</span></span>
                <span class="settings-arrow">&rsaquo;</span>
            </button>

            <button class="settings-card" type="button" data-settings-card="recursos" data-settings-search="puestos equipamiento sillas mesas camillas cabinas capacidad reservas simultaneas">
                <span class="settings-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="M4 20V9h16v11M7 9V5h10v4M8 20v-5h8v5"/></svg></span>
                <span><b>Puestos y equipamiento</b><span>Sillas, mesas, camillas y capacidad simultanea del negocio.</span></span>
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

            <article class="card" id="numero-cliente">
                <div class="section-title">
                    <div>
                        <h2>Numero del cliente</h2>
                        <span class="subtitle">Actualiza el telefono y correo despues de validar un codigo enviado al movil actual.</span>
                    </div>
                    <span class="status {{ ($clinic?->phone || $currentUser?->mobile_phone) ? 'ok' : 'wait' }}">{{ ($clinic?->phone || $currentUser?->mobile_phone) ? 'Configurado' : 'Pendiente' }}</span>
                </div>

                <form method="POST" action="{{ route('settings.contact-code') }}">
                    @csrf
                    <div class="grid-3">
                        <div>
                            <label for="contact_mobile_phone">Telefono movil</label>
                            <input id="contact_mobile_phone" name="mobile_phone" value="{{ old('mobile_phone', $currentUser?->mobile_phone ?: $clinic?->phone) }}" autocomplete="tel">
                            @error('mobile_phone') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="contact_email">Correo electronico</label>
                            <input id="contact_email" name="email" type="email" value="{{ old('email', $currentUser?->email ?: $clinic?->email) }}" autocomplete="email">
                            @error('email') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label>Movil donde llega el codigo</label>
                            <input value="{{ $currentUser?->mobile_phone ?: 'Pendiente' }}" readonly>
                        </div>
                    </div>
                    <div class="actions" style="justify-content:flex-end;margin-top:18px;">
                        <button class="btn primary" type="submit">Enviar codigo SMS</button>
                    </div>
                </form>

                @if (is_array($pendingContactChange))
                    <form method="POST" action="{{ route('settings.contact-confirm') }}" style="margin-top:18px;border-top:1px solid var(--line);padding-top:18px;">
                        @csrf
                        <div class="grid-3">
                            <div>
                                <label>Nuevo telefono pendiente</label>
                                <input value="{{ $pendingContactChange['mobile_phone'] ?? 'Pendiente' }}" readonly>
                            </div>
                            <div>
                                <label>Nuevo correo pendiente</label>
                                <input value="{{ $pendingContactChange['email'] ?? 'Pendiente' }}" readonly>
                            </div>
                            <div>
                                <label for="contact_code">Codigo recibido por SMS</label>
                                <input id="contact_code" name="code" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code">
                                @error('code') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="actions" style="justify-content:flex-end;margin-top:18px;">
                            <button class="btn primary" type="submit">Confirmar cambio</button>
                        </div>
                    </form>
                @endif

                <div class="grid-3" style="margin-top:18px;">
                    <div>
                        <label>Telefono del salon</label>
                        <input value="{{ $clinic?->phone ?: 'Pendiente' }}" readonly>
                    </div>
                    <div>
                        <label>Telefono del usuario</label>
                        <input value="{{ $currentUser?->mobile_phone ?: 'Pendiente' }}" readonly>
                    </div>
                    <div>
                        <label>Email de contacto</label>
                        <input value="{{ $clinic?->email ?: ($currentUser?->email ?: 'Pendiente') }}" readonly>
                    </div>
                </div>
            </article>

            <article class="card integration-status-bar">
                <div class="section-title"><h2>Estado de integraciones</h2></div>
                <div class="item">
                    <div><b>Twilio SMS</b><span>{{ config('services.twilio.from') ? 'Numero configurado para mensajes salientes.' : 'Agrega TWILIO_FROM en .env.' }}</span></div>
                    <span class="status integration-status {{ config('services.twilio.from') ? 'ok' : 'wait' }}">{{ config('services.twilio.from') ? 'Activo' : 'Pendiente' }}</span>
                </div>
                <div class="item">
                    <div><b>Google Calendar</b><span>{{ $clinic?->google_calendar_summary ?? 'Calendario no conectado.' }}</span></div>
                    <span class="status integration-status {{ $clinic?->google_connected_at ? 'ok' : 'wait' }}">{{ $clinic?->google_connected_at ? 'Activo' : 'Pendiente' }}</span>
                </div>
                <div class="item">
                    <div><b>Voz Twilio</b><span>Voces disponibles para la secretaria virtual: gratis, Standard, Neural y Generative.</span></div>
                    <span class="status integration-status ok">Activo</span>
                </div>
                <div class="item">
                    <div><b>Stripe</b><span>{{ $clinic?->plan?->name ? 'Plan '.$clinic->plan->name : 'Plan no asignado.' }}</span></div>
                    <span class="status integration-status {{ in_array($clinic?->subscription_status, ['active', 'trial'], true) ? 'ok' : 'wait' }}">{{ ucfirst($clinic?->subscription_status ?? 'pendiente') }}</span>
                </div>
                <div class="item">
                    <div><b>Email</b><span>{{ config('mail.default') === 'log' ? 'Los correos estan guardandose en logs.' : 'Mailer '.config('mail.default').' configurado.' }}</span></div>
                    <span class="status integration-status {{ config('mail.default') === 'log' ? 'wait' : 'ok' }}">{{ config('mail.default') === 'log' ? 'Log' : 'Activo' }}</span>
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

        <section class="settings-panel" data-settings-panel="google-calendar" data-settings-title="Google Calendar y especialistas">
            <article class="card" id="google-calendar">
                <div class="section-title">
                    <div>
                        <h2>Google Calendar y especialistas</h2>
                        <span class="subtitle">Cada calendario puede representar la agenda de un especialista distinto.</span>
                    </div>
                    <span class="status {{ $clinic?->google_connected_at ? 'ok' : 'wait' }}">
                        {{ $clinic?->google_connected_at ? 'Conectado' : ($clinic?->google_ever_synced_at ? 'Desconectado' : 'Sin conectar') }}
                    </span>
                </div>

                @if (! $clinic?->google_connected_at)
                    <p class="subtitle" style="margin-top:14px;">Conecta la cuenta de Google para detectar sus calendarios. Las citas ya importadas permaneceran visibles si luego desconectas la cuenta.</p>
                    <div class="actions" style="margin-top:18px;">
                        <a class="btn primary" href="{{ route('google-calendar.connect') }}">Conectar Google Calendar</a>
                    </div>
                @else
                    <div class="grid-3" style="margin-top:16px;">
                        <div><label>Cuenta conectada</label><input value="{{ $clinic->google_calendar_summary ?: 'Google Calendar' }}" readonly></div>
                        <div><label>Ultima sincronizacion</label><input value="{{ $clinic->google_last_synced_at?->timezone($timezone)->format('d/m/Y H:i') ?: 'Pendiente' }}" readonly></div>
                        <div><label>Calendarios detectados</label><input value="{{ $googleCalendarMappings->where('is_available', true)->count() }}" readonly></div>
                    </div>

                    <form class="calendar-organization" method="POST" action="{{ route('google-calendar.organize') }}">
                        @csrf
                        <h3>¿Cómo quieres organizar Google Calendar?</h3>
                        <div class="calendar-organization-options">
                            <label class="calendar-organization-option">
                                <input type="radio" name="organization_mode" value="per_specialist" @checked($clinic->google_calendar_organization_mode === 'per_specialist') required>
                                <span><b>Un calendario por especialista</b><span class="calendar-recommended">Recomendado</span><span>Creamos y asignamos automáticamente las agendas que falten.</span></span>
                            </label>
                            <label class="calendar-organization-option">
                                <input type="radio" name="organization_mode" value="single" @checked($clinic->google_calendar_organization_mode === 'single') required>
                                <span><b>Un único calendario para todo el salón</b><span>Todas las citas se sincronizan con el calendario principal.</span></span>
                            </label>
                            <label class="calendar-organization-option">
                                <input type="radio" name="organization_mode" value="existing" @checked($clinic->google_calendar_organization_mode === 'existing') required>
                                <span><b>Asignar calendarios existentes</b><span>Detectamos los calendarios de Google para que los relaciones manualmente.</span></span>
                            </label>
                        </div>
                        <div class="actions" style="justify-content:flex-end;margin-top:14px;"><button class="btn primary" type="submit">Guardar y configurar</button></div>
                    </form>

                    <div class="actions" style="margin-top:18px;">
                        <form method="POST" action="{{ route('google-calendar.detect') }}">@csrf<button class="btn primary" type="submit">Detectar calendarios</button></form>
                        <form method="POST" action="{{ route('google-calendar.sync') }}">@csrf<button class="btn" type="submit">Sincronizar ahora</button></form>
                        <form method="POST" action="{{ route('google-calendar.disconnect') }}" onsubmit="return confirm('¿Desconectar Google Calendar? Las citas importadas no se borraran.');">@csrf<button class="btn" type="submit">Desconectar</button></form>
                    </div>

                    @if (! $clinic->google_calendar_organization_mode)
                        <div class="forwarding-guide"><b>Elige cómo organizar las agendas</b>No sincronizaremos citas hasta que confirmes una de las opciones anteriores.</div>
                    @elseif ($clinic->google_calendar_organization_mode === 'single')
                        <div class="forwarding-guide"><b>Calendario único activo</b>Todas las especialistas comparten el calendario principal de la cuenta conectada.</div>
                    @elseif ($googleCalendarMappings->isEmpty())
                        <div class="forwarding-guide"><b>Aun no hemos detectado calendarios</b>Pulsa “Detectar calendarios” y luego asigna cada calendario al especialista correspondiente.</div>
                    @else
                        <form method="POST" action="{{ route('google-calendar.mappings.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="calendar-map-list">
                                @foreach ($googleCalendarMappings as $mapping)
                                    <div class="calendar-map-row" style="{{ $mapping->is_available ? '' : 'opacity:.58;' }}">
                                        <div class="calendar-map-name">
                                            <b>{{ $mapping->google_calendar_name }} {{ $mapping->is_primary ? '(principal)' : '' }}</b>
                                            <span>{{ $mapping->is_available ? 'Detectado en Google' : 'Ya no esta disponible en Google' }} · Acceso: {{ $mapping->access_role ?: 'sin identificar' }}</span>
                                        </div>
                                        <div>
                                            <label for="calendar_stylist_{{ $mapping->id }}">Especialista</label>
                                            <select id="calendar_stylist_{{ $mapping->id }}" name="calendars[{{ $mapping->id }}][stylist_id]" {{ $mapping->is_available ? '' : 'disabled' }}>
                                                <option value="">Google interno (sin asignar)</option>
                                                @foreach ($googleStylists as $stylist)
                                                    <option value="{{ $stylist->id }}" @selected($mapping->stylist_id === $stylist->id)>{{ $stylist->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <label class="calendar-map-enabled">
                                            <input type="hidden" name="calendars[{{ $mapping->id }}][enabled]" value="0">
                                            <input type="checkbox" name="calendars[{{ $mapping->id }}][enabled]" value="1" @checked($mapping->is_enabled) {{ $mapping->is_available ? '' : 'disabled' }}>
                                            Sincronizar
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="forwarding-guide"><b>Como funciona la asignacion</b>Las citas de cada calendario se colocaran con el especialista elegido. Los calendarios sin asignar se mostraran bajo el personal interno “Google” y nunca se ofreceran a los clientes.</div>
                            <div class="actions" style="justify-content:flex-end;margin-top:18px;"><button class="btn primary" type="submit">Guardar asignaciones</button></div>
                        </form>
                    @endif
                @endif
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
                        <b>{{ $activeNotificationRules }} de {{ count($notificationBooleanKeys) }}</b>
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
                    <div class="grid-2" style="margin-top:6px;">
                        <div>
                            <label for="appointment_reminder_call_hours_before">Llamada recordatorio</label>
                            <input id="appointment_reminder_call_hours_before" name="appointment_reminder_call_hours_before" type="number" min="1" max="168" value="{{ old('appointment_reminder_call_hours_before', $reminderCallHoursBefore) }}">
                            <span class="subtitle" style="display:block;margin-top:8px;">Horas antes de la cita. Maximo 72 horas.</span>
                            @error('appointment_reminder_call_hours_before') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="appointment_reminder_sms_hours_before">SMS recordatorio</label>
                            <input id="appointment_reminder_sms_hours_before" name="appointment_reminder_sms_hours_before" type="number" min="1" max="168" value="{{ old('appointment_reminder_sms_hours_before', $reminderSmsHoursBefore) }}">
                            <span class="subtitle" style="display:block;margin-top:8px;">Horas antes de la cita. Maximo 72 horas.</span>
                            @error('appointment_reminder_sms_hours_before') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="actions" style="justify-content:flex-end;">
                        <button class="btn primary" type="submit">Guardar notificaciones</button>
                    </div>
                </form>

            </article>
        </section>

        <section class="settings-panel" data-settings-panel="servicios" data-settings-title="Configuracion de servicios">
            <article class="card" id="desvio-llamadas">
                <div class="section-title">
                    <div>
                        <h2>Activa a Nora en tu linea habitual</h2>
                        <span class="subtitle">Elige cuando quieres desviar las llamadas y te enviaremos por SMS exactamente lo que debes marcar.</span>
                    </div>
                    <span class="status {{ $callForwardingSent ? 'ok' : 'wait' }}">{{ $callForwardingSent ? 'Instrucciones enviadas' : 'Paso inicial' }}</span>
                </div>

                <form method="POST" action="{{ route('settings.call-forwarding-instructions') }}">
                    @csrf
                    <div class="forwarding-options">
                        <label class="forwarding-option recommended">
                            <input type="radio" name="mode" value="no_answer" @checked(old('mode', $callForwardingMode) === 'no_answer')>
                            <span class="forwarding-option-body"><b>Cuando no contesten</b><span>El telefono del salon suena primero. Si nadie responde, Nora atiende la llamada.</span></span>
                        </label>
                        <label class="forwarding-option">
                            <input type="radio" name="mode" value="always" @checked(old('mode', $callForwardingMode) === 'always')>
                            <span class="forwarding-option-body"><b>Todas las llamadas</b><span>Nora atiende desde el primer momento, sin hacer sonar antes la linea habitual.</span></span>
                        </label>
                        <label class="forwarding-option">
                            <input type="radio" name="mode" value="busy_unreachable" @checked(old('mode', $callForwardingMode) === 'busy_unreachable')>
                            <span class="forwarding-option-body"><b>Ocupado, apagado o sin cobertura</b><span>Nora entra cuando la linea comunica o el movil no esta disponible.</span></span>
                        </label>
                        <label class="forwarding-option">
                            <input type="radio" name="mode" value="outside_hours" @checked(old('mode', $callForwardingMode) === 'outside_hours')>
                            <span class="forwarding-option-body"><b>Noches y fines de semana</b><span>Recibes los codigos para activar al cerrar y desactivar al abrir. La programacion automatica depende del operador.</span></span>
                        </label>
                        <label class="forwarding-option">
                            <input type="radio" name="mode" value="operator_help" @checked(old('mode', $callForwardingMode) === 'operator_help')>
                            <span class="forwarding-option-body"><b>Mi operador no acepta codigos</b><span>Te enviamos el numero de destino y las instrucciones para solicitar el desvio al operador.</span></span>
                        </label>
                    </div>

                    <div class="grid-3" style="margin-top:18px;">
                        <div>
                            <label>País detectado</label>
                            <input value="{{ $forwardingService->countryName($callForwardingCountry) }}" readonly>
                            <small style="display:block;margin-top:6px;color:var(--muted);">Detectado mediante el prefijo del teléfono del salón.</small>
                        </div>
                        <div>
                            <label for="forwarding_operator">Operador de la línea del salón</label>
                            <select id="forwarding_operator" name="operator" required>
                                @foreach ($callForwardingOperators as $operatorCode => $operatorName)
                                    <option value="{{ $operatorCode }}" @selected(old('operator', $callForwardingOperator) === $operatorCode)>{{ $operatorName }}</option>
                                @endforeach
                            </select>
                            @error('operator') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                        </div>
                        <div data-forwarding-delay>
                            <label for="forwarding_ring_seconds">Tiempo antes de que responda Nora</label>
                            <select id="forwarding_ring_seconds" name="ring_seconds">
                                @foreach ([15 => '15 segundos (3-4 tonos)', 20 => '20 segundos (4-5 tonos)', 25 => '25 segundos (5-6 tonos)', 30 => '30 segundos (6 tonos aprox.)'] as $seconds => $label)
                                    <option value="{{ $seconds }}" @selected((int) old('ring_seconds', $callForwardingSeconds) === $seconds)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Numero de Secretary365</label>
                            <input value="{{ $clinic?->twilio_phone_number ?? 'Asigna primero un numero' }}" readonly>
                        </div>
                        <div>
                            <label for="forwarding_recipient">Movil que recibe las instrucciones</label>
                            <input id="forwarding_recipient" name="recipient" value="{{ old('recipient', $notificationPreferences['call_forwarding_recipient'] ?? $currentUser?->mobile_phone ?? $clinic?->phone) }}" autocomplete="tel" placeholder="+34 600 000 000">
                            @error('recipient') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="forwarding-guide">
                        <b>Que hacer cuando llegue el SMS</b>
                        1. Confirma que el país y el operador indicados son correctos. 2. Abre la aplicación Teléfono en el móvil del salón. 3. Marca la secuencia recibida y pulsa llamar. 4. Espera la confirmación y haz una llamada de prueba. Si el operador no admite códigos, el SMS te indicará cómo solicitarlo sin arriesgar una configuración incorrecta.
                    </div>

                    <div class="actions" style="justify-content:flex-end;margin-top:18px;">
                        @if ($clinic?->twilio_phone_number)
                            <button class="btn primary" type="submit">Enviar instrucciones por SMS</button>
                        @else
                            <span class="btn">Asigna primero tu numero en Informacion del negocio</span>
                        @endif
                    </div>
                </form>
            </article>

        </section>

        <section class="settings-panel" data-settings-panel="recursos" data-settings-title="Puestos y equipamiento">
            <article class="card" id="recursos">
                <div class="section-title">
                    <div><h2>Puestos y equipamiento</h2><span class="subtitle">Evita aceptar mas citas simultaneas que puestos fisicos disponibles.</span></div>
                    <span class="status {{ $facilityResources->isNotEmpty() ? 'ok' : 'wait' }}">{{ $facilityResources->count() }} tipos</span>
                </div>

                <form method="POST" action="/ajustes/recursos" class="grid-3" style="align-items:end;margin-top:16px;">
                    @csrf
                    <div>
                        <label for="resource_name">Nombre del recurso</label>
                        <input id="resource_name" name="resource_name" autocomplete="off" placeholder="Escribe un recurso nuevo" required>
                        @error('resource_name') <div class="danger" style="margin-top:7px;">{{ $message }}</div> @enderror
                    </div>
                    <div><label for="resource_capacity">Cantidad disponible</label><input id="resource_capacity" name="capacity" type="number" min="1" max="999" value="1" required></div>
                    <button class="btn primary" type="submit">Agregar recurso</button>
                    <div class="actions" style="grid-column:1/-1;gap:5px;">
                        @foreach(['Sillas de peluqueria','Mesas de manicura','Camillas de masaje','Sillones de pedicura','Cabinas de estetica'] as $resourceSuggestion)
                            <button class="btn" type="button" data-resource-suggestion="{{ $resourceSuggestion }}" style="min-height:28px;padding:0 8px;font-size:11px;">{{ $resourceSuggestion }}</button>
                        @endforeach
                    </div>
                </form>

                @if($facilityResources->isNotEmpty())
                    <div style="display:grid;gap:10px;margin-top:18px;">
                        @foreach($facilityResources as $resource)
                            <div style="display:grid;grid-template-columns:1fr 150px auto;gap:10px;align-items:end;padding:12px;border:1px solid var(--line);border-radius:8px;background:var(--soft);">
                                <form id="resource-form-{{ $resource->id }}" method="POST" action="/ajustes/recursos/{{ $resource->id }}">@csrf @method('PUT')</form>
                                <div><label>Recurso</label><input form="resource-form-{{ $resource->id }}" name="resource_name" value="{{ $resource->name }}" autocomplete="off" required></div>
                                <div><label>Cantidad</label><input form="resource-form-{{ $resource->id }}" name="capacity" type="number" min="1" max="999" value="{{ $resource->capacity }}" required></div>
                                <div class="actions" style="gap:6px;"><input form="resource-form-{{ $resource->id }}" type="hidden" name="is_active" value="0"><label style="display:flex;align-items:center;gap:6px;margin:0;"><input form="resource-form-{{ $resource->id }}" type="checkbox" name="is_active" value="1" style="width:auto;" @checked($resource->is_active)> Activo</label><button form="resource-form-{{ $resource->id }}" class="btn" type="submit">Guardar</button><form method="POST" action="/ajustes/recursos/{{ $resource->id }}" onsubmit="return confirm('¿Eliminar este recurso?');">@csrf @method('DELETE')<button class="btn" type="submit">Eliminar</button></form></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>

            <article class="card">
                <div class="section-title"><div><h2>Recurso necesario por servicio</h2><span class="subtitle">Si un servicio no necesita un puesto limitado, selecciona “Sin recurso”.</span></div></div>
                <form method="POST" action="/ajustes/recursos/asignaciones">
                    @csrf
                    <div style="display:grid;gap:9px;margin-top:14px;">
                        @foreach($resourceServices as $service)
                            <div style="display:grid;grid-template-columns:1fr minmax(210px,320px) 110px;gap:10px;align-items:end;padding:10px;border-bottom:1px solid var(--line);">
                                <b>{{ $service->name }}</b>
                                <div><label>Recurso</label><select name="assignments[{{ $service->id }}][resource_id]"><option value="">Sin recurso limitado</option>@foreach($facilityResources as $resource)<option value="{{ $resource->id }}" @selected($service->facility_resource_id === $resource->id)>{{ $resource->name }} ({{ $resource->capacity }})</option>@endforeach</select></div>
                                <div><label>Unidades</label><input name="assignments[{{ $service->id }}][units]" type="number" min="1" max="99" value="{{ $service->resource_units ?: 1 }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="actions" style="justify-content:flex-end;margin-top:16px;"><button class="btn primary" type="submit">Guardar asignaciones</button></div>
                </form>
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
                                <span>{{ $voice['description'] }} @if (! empty($voice['gender'])) · {{ $voice['gender'] }} @endif @if (! empty($voice['badge'])) · {{ $voice['badge'] }} @endif</span>
                            </div>
                            <div class="actions" style="margin:0;">
                                <span class="status integration-status {{ $isActiveVoice ? 'ok' : 'wait' }}">{{ $isActiveVoice ? 'Activa' : 'Disponible' }}</span>
                                @if ($canPreview)
                                    @if ($isTwilioVoice)
                                        <form method="POST" action="{{ route('secretary-voice.preview-call') }}">
                                            @csrf
                                            <input type="hidden" name="voice" value="{{ $voiceId }}">
                                            <button class="btn" type="submit">Probar llamada</button>
                                        </form>
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
        document.querySelectorAll('[data-resource-suggestion]').forEach((button) => button.addEventListener('click', () => {
            const input = document.getElementById('resource_name');
            input.value = button.dataset.resourceSuggestion;
            input.focus();
        }));

        const settingsCards = Array.from(document.querySelectorAll('[data-settings-card]'));
        const settingsPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));
        const settingsSearch = document.getElementById('settings-search');
        const settingsEmpty = document.getElementById('settings-empty');
        const settingsShell = document.querySelector('.settings-shell');
        const settingsBack = document.querySelector('.settings-back');
        const settingsDetailTitle = document.getElementById('settings-detail-title');
        const forwardingModeInputs = Array.from(document.querySelectorAll('input[name="mode"]'));
        const forwardingDelay = document.querySelector('[data-forwarding-delay]');
        const syncForwardingDelay = () => {
            const selectedMode = forwardingModeInputs.find((input) => input.checked)?.value;
            if (forwardingDelay) forwardingDelay.hidden = selectedMode !== 'no_answer';
        };
        forwardingModeInputs.forEach((input) => input.addEventListener('change', syncForwardingDelay));
        syncForwardingDelay();

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
            if (secretaryVoiceAudio) {
                secretaryVoiceAudio.pause();
                secretaryVoiceAudio.currentTime = 0;
            }
        }

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
                        const errorMessage = await response.text();
                        throw new Error(errorMessage || 'No se pudo generar la prueba.');
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
