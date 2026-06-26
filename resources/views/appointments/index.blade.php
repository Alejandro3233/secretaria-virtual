@extends('layouts.app')

@section('title', 'Citas - Secretaria Virtual')
@section('page_title', 'Citas')
@section('page_subtitle', 'Gestiona reservas, confirmaciones, cambios y cancelaciones del salon.')
@section('page_actions')
    <a class="btn" href="/agenda">Ver agenda</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    <style>
        .appointment-filter {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 6px;
            overflow: hidden;
            background: #fffafd;
        }

        .appointment-filter a {
            min-width: 62px;
            padding: 7px 10px;
            border-right: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-align: center;
            text-decoration: none;
            white-space: nowrap;
        }

        .appointment-filter a:last-child {
            border-right: 0;
        }

        .appointment-filter a.active {
            background: var(--brand);
            color: white;
        }

        .appointment-list-toolbar { display: grid; grid-template-columns: minmax(150px, 1fr) auto minmax(258px, 1fr); align-items: center; gap: 18px; margin-bottom: 10px; }
        .appointment-list-toolbar .appointment-filter { justify-self: end; }
        .appointment-period-nav { display: flex; align-items: center; justify-content: center; gap: 8px; }
        .appointment-period-nav a { width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid var(--line); border-radius: 6px; background: white; color: var(--ink); font-size: 20px; font-weight: 900; }
        .appointment-period-nav b { min-width: 220px; color: var(--ink); font-size: 14px; text-align: center; }

        @media (max-width: 620px) {
            .appointment-filter {
                width: 100%;
            }
            .appointment-period-nav { justify-content: center; }
            .appointment-period-nav b { min-width: 160px; }
        }

        @media (max-width: 900px) {
            .appointment-list-toolbar { grid-template-columns: 1fr; align-items: stretch; }
            .appointment-list-toolbar .appointment-filter { justify-self: stretch; }
        }

        .reminder-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 118px;
        }

        .reminder-toggle {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 900;
            color: #475569;
            white-space: nowrap;
        }

        .reminder-toggle input {
            width: 16px;
            height: 16px;
            accent-color: #c72664;
        }

        .reminder-toggle input:disabled + span {
            color: #94a3b8;
        }

        .reminder-call-result {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .reminder-call-result.ok { color: var(--green); }
        .reminder-call-result.wait { color: var(--amber); }
        .reminder-call-result.danger { color: #991b1b; }
        .reminder-call-result.reminded {
            display: block;
            background: transparent;
            color: var(--green);
        }

        .appointment-card-list { display: grid; gap: 10px; margin-top: 16px; }
        .appointment-card-header,
        .appointment-card {
            display: grid;
            grid-template-columns: 110px minmax(190px, 1.4fr) minmax(130px, .8fr) 120px minmax(120px, .8fr) 80px minmax(155px, 1fr) 128px;
            align-items: center;
            gap: 12px;
        }
        .appointment-card-header {
            padding: 0 16px 0 21px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .appointment-card {
            min-height: 86px;
            border: 1px solid var(--line);
            border-left: 5px solid var(--amber);
            border-radius: 10px;
            padding: 14px 16px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(24, 18, 22, .05);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .appointment-card > * { min-width: 0; }
        .appointment-card:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(24, 18, 22, .09); }
        .appointment-card.appointment-confirmed { border-left-color: #15803d; }
        .appointment-card.appointment-pending { border-left-color: #ca8a04; }
        .appointment-card.appointment-urgent-light { border-left-color: #f87171; }
        .appointment-card.appointment-urgent-medium { border-left-color: #dc2626; }
        .appointment-card.appointment-urgent-high { border-left-color: #7f1d1d; }
        .appointment-card.appointment-cancelled { border-left-color: #4b5563; background: #f8fafc; opacity: .82; }
        .appointment-card-time strong, .appointment-card-main strong { display: block; color: var(--ink); font-size: 15px; font-weight: 900; }
        .appointment-card-time strong { color: var(--brand); font-size: 17px; }
        .appointment-card-time span, .appointment-card-main span, .appointment-card-detail > span { display: block; margin-top: 4px; color: var(--muted); font-size: 13px; }
        .appointment-card-main strong { display: flex; align-items: center; gap: 4px; }
        .appointment-card-detail b { display: block; overflow: hidden; color: var(--ink); font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
        .appointment-card-duration { color: var(--ink); font-size: 13px; font-weight: 800; }
        .appointment-card-action { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
        .appointment-card-action .btn { min-height: 32px; padding: 0 10px; font-size: 12px; }
        .payment-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }
        .payment-badge.paid { background: #dcfce7; color: #166534; }
        .payment-badge.pending { background: #fef3c7; color: #92400e; }
        .checkout-modal[hidden] { display: none; }
        .checkout-modal {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(15, 23, 42, .45);
        }
        .checkout-dialog {
            width: min(560px, 100%);
            max-height: min(760px, calc(100vh - 40px));
            overflow: auto;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        }
        .checkout-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
        }
        .checkout-head h2 { margin: 0; font-size: 20px; }
        .checkout-head span { display: block; margin-top: 4px; color: var(--muted); font-size: 13px; font-weight: 800; }
        .checkout-close {
            width: 34px;
            height: 34px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: white;
            color: var(--ink);
            cursor: pointer;
            font-size: 22px;
            line-height: 1;
        }
        .checkout-body { display: grid; gap: 16px; padding: 20px; }
        .checkout-amount input { font-size: 28px; font-weight: 900; }
        .payment-methods { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .payment-method {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            color: var(--ink);
            font-size: 13px;
            font-weight: 900;
            cursor: pointer;
        }
        .payment-method input { width: auto; min-height: auto; }
        .payment-method:has(input:checked) { border-color: var(--brand); background: #fff1f6; color: var(--brand); }
        .checkout-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 0 20px 20px; }

        @media (max-width: 1180px) {
            .appointment-card-header,
            .appointment-card { grid-template-columns: 100px minmax(180px, 1.3fr) minmax(120px, .8fr) 110px minmax(110px, .7fr) 70px minmax(145px, 1fr) 118px; gap: 9px; }
            .appointment-card { padding-left: 13px; padding-right: 13px; }
            .appointment-card-header { padding-left: 18px; padding-right: 13px; }
        }

        @media (min-width: 901px) {
            html:not(.sidebar-collapsed) .appointment-card-header,
            html:not(.sidebar-collapsed) .appointment-card {
                grid-template-columns: 100px minmax(180px, 1.45fr) minmax(120px, .8fr) 110px 70px minmax(140px, 1fr) 118px;
                gap: 9px;
            }
            html:not(.sidebar-collapsed) .appointment-card {
                padding: 13px;
            }
            html:not(.sidebar-collapsed) .appointment-card-header span:nth-child(5),
            html:not(.sidebar-collapsed) .appointment-card > .appointment-card-detail:nth-of-type(5) {
                display: none;
            }
        }

        @media (min-width: 901px) and (max-width: 1240px) {
            .appointment-card-header { display: none; }
            .appointment-card {
                grid-template-columns: 92px minmax(190px, 1.35fr) minmax(112px, .75fr) minmax(112px, .75fr) 70px;
                grid-template-rows: auto auto;
                gap: 10px 12px;
                align-items: center;
                padding: 13px;
            }
            .appointment-card-time { grid-column: 1; grid-row: 1 / span 2; }
            .appointment-card-main { grid-column: 2; grid-row: 1; }
            .appointment-card > .appointment-card-detail:nth-of-type(3) { grid-column: 3; grid-row: 1; }
            .appointment-card > .appointment-card-detail:nth-of-type(4) { grid-column: 4; grid-row: 1; }
            .appointment-card > .appointment-card-detail:nth-of-type(5) { grid-column: 2; grid-row: 2; }
            .appointment-card-duration { grid-column: 3; grid-row: 2; }
            .appointment-card > div:nth-of-type(7) { grid-column: 4 / span 2; grid-row: 2; }
            .appointment-card-action { grid-column: 5; grid-row: 1; justify-self: end; }
            .reminder-controls { justify-content: flex-end; }
        }

        @media (min-width: 901px) and (max-width: 1240px) {
            html:not(.sidebar-collapsed) .appointment-card-header {
                display: grid;
            }
            html:not(.sidebar-collapsed) .appointment-card-header,
            html:not(.sidebar-collapsed) .appointment-card {
                grid-template-columns: 100px minmax(180px, 1.45fr) minmax(120px, .8fr) 110px 70px minmax(140px, 1fr) 118px;
                grid-template-rows: auto;
                gap: 9px;
            }
            html:not(.sidebar-collapsed) .appointment-card-time,
            html:not(.sidebar-collapsed) .appointment-card-main,
            html:not(.sidebar-collapsed) .appointment-card > .appointment-card-detail:nth-of-type(3),
            html:not(.sidebar-collapsed) .appointment-card > .appointment-card-detail:nth-of-type(4),
            html:not(.sidebar-collapsed) .appointment-card-duration,
            html:not(.sidebar-collapsed) .appointment-card > div:nth-of-type(7),
            html:not(.sidebar-collapsed) .appointment-card-action {
                grid-column: auto;
                grid-row: auto;
            }
            html:not(.sidebar-collapsed) .appointment-card-header span:nth-child(5),
            html:not(.sidebar-collapsed) .appointment-card > .appointment-card-detail:nth-of-type(5) {
                display: none;
            }
            html:not(.sidebar-collapsed) .appointment-card-action {
                justify-self: end;
            }
        }

        @media (max-width: 900px) {
            .appointment-card-header { display: none; }
            .appointment-card { grid-template-columns: 72px minmax(0, 1fr); gap: 12px; padding: 13px; }
            .appointment-card > *:not(.appointment-card-time):not(.appointment-card-main) { grid-column: 2; }
            .appointment-card-action { justify-self: start; }
            .payment-methods { grid-template-columns: 1fr; }
        }

    </style>

    @if (session('appointment_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('appointment_status') }}
        </div>
    @endif

    @if (session('appointment_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('appointment_error') }}
        </div>
    @endif

    @php
        $previousDate = match ($period) {
            'week' => $selectedDate->copy()->subWeek(),
            'month' => $selectedDate->copy()->subMonthNoOverflow(),
            default => $selectedDate->copy()->subDay(),
        };
        $nextDate = match ($period) {
            'week' => $selectedDate->copy()->addWeek(),
            'month' => $selectedDate->copy()->addMonthNoOverflow(),
            default => $selectedDate->copy()->addDay(),
        };
        $periodLabel = match ($period) {
            'week' => $selectedDate->copy()->startOfWeek()->locale('es')->isoFormat('D MMM').' - '.$selectedDate->copy()->endOfWeek()->locale('es')->isoFormat('D MMM YYYY'),
            'month' => ucfirst($selectedDate->locale('es')->isoFormat('MMMM [de] YYYY')),
            default => ucfirst($selectedDate->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')),
        };
    @endphp

    <article class="card">
        <div class="appointment-list-toolbar">
            <div>
                <h2>Listado de citas</h2>
            </div>
            <div class="appointment-period-nav" aria-label="Navegacion por fechas">
                <a href="/citas?{{ http_build_query(['period' => $period, 'date' => $previousDate->format('Y-m-d')]) }}" aria-label="Anterior">&lsaquo;</a>
                <b>{{ $periodLabel }}</b>
                <a href="/citas?{{ http_build_query(['period' => $period, 'date' => $nextDate->format('Y-m-d')]) }}" aria-label="Siguiente">&rsaquo;</a>
            </div>
            <nav class="appointment-filter" aria-label="Filtro de citas">
                <a class="{{ $period === 'day' ? 'active' : '' }}" href="/citas?{{ http_build_query(['period' => 'day', 'date' => now($timezone)->format('Y-m-d')]) }}">Dia</a>
                <a class="{{ $period === 'week' ? 'active' : '' }}" href="/citas?{{ http_build_query(['period' => 'week', 'date' => now($timezone)->format('Y-m-d')]) }}">Semana</a>
                <a class="{{ $period === 'month' ? 'active' : '' }}" href="/citas?{{ http_build_query(['period' => 'month', 'date' => now($timezone)->format('Y-m-d')]) }}">Mes</a>
            </nav>
        </div>

        @if ($appointments->isEmpty())
            <div class="section-title" style="margin-bottom:0;">
                <div>
                    <h2>No hay citas para este filtro</h2>
                    <span class="subtitle">Cambia el filtro, crea una cita nueva o sincroniza Google Calendar para importar reservas.</span>
                </div>
                <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
            </div>
        @else
            <div class="appointment-card-list">
                    <div class="appointment-card-header" aria-hidden="true">
                        <span>Fecha</span>
                        <span>Cliente</span>
                        <span>Telefono</span>
                        <span>Estado</span>
                        <span>Estilista</span>
                        <span>Tiempo</span>
                        <span>Recordatorio</span>
                        <span>Acciones</span>
                    </div>
                    @foreach ($appointments as $appointment)
                        @php
                            $duration = $appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : null;
                            $clientPhone = trim((string) $appointment->client?->phone);
                            $clientPhoneLabel = $clientPhone !== '' && ! str_starts_with($clientPhone, 'google:') ? $clientPhone : 'Sin telefono';
                            $clientName = trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? ''));
                            $clientName = trim(preg_replace('/^Google Calendar\s*-\s*/i', '', $clientName));
                            $serviceName = trim((string) ($appointment->service?->name ?? $appointment->reason ?? 'Cita'));
                            $serviceName = strcasecmp($serviceName, 'Google Calendar') === 0 ? '' : preg_replace('/^Google Calendar\s*-\s*/i', '', $serviceName);
                            $canRemind = ! in_array($appointment->status, ['cancelled', 'canceled'], true) && $clientPhone !== '' && ! str_starts_with($clientPhone, 'google:');
                            $callEnabled = $appointment->reminder_call_enabled ?? false;
                            $smsEnabled = $appointment->reminder_sms_enabled ?? false;
                            $voiceReminderAnswered = in_array($appointment->reminder_call_status, ['completed', 'answered', 'in-progress'], true);
                            if ($voiceReminderAnswered) $callEnabled = false;
                            $callResultLabel = match ($appointment->reminder_call_status) {
                                'completed', 'answered', 'sent' => 'Contestó',
                                'no-answer' => 'No contestó',
                                'busy' => 'Ocupado',
                                'failed', 'canceled' => 'Fallida',
                                'queued', 'initiated', 'ringing', 'in-progress' => 'En proceso',
                                default => null,
                            };
                            $callResultClass = match ($appointment->reminder_call_status) {
                                'completed', 'answered', 'sent' => 'ok',
                                'no-answer', 'busy', 'failed', 'canceled' => 'danger',
                                'queued', 'initiated', 'ringing', 'in-progress' => 'wait',
                                default => '',
                            };
                            $statusLabel = match ($appointment->status) {
                                'confirmed' => 'Confirmada',
                                'completed' => 'Completada',
                                'pending' => 'Pendiente',
                                'cancelled', 'canceled' => 'Cancelada',
                                default => ucfirst((string) $appointment->status),
                            };
                            $statusClass = match ($appointment->status) {
                                'confirmed', 'completed' => 'ok',
                                'cancelled', 'canceled' => 'cancelled-status',
                                default => 'wait',
                            };
                            $latestPayment = $appointment->latest_payment;
                            $paymentMethodLabel = match ($latestPayment?->method) {
                                'cash' => 'Efectivo',
                                'stripe' => 'Tarjeta',
                                'other' => 'Otro',
                                default => null,
                            };
                            $paymentBadge = $latestPayment
                                ? (($latestPayment->status === 'paid' ? 'Pagado' : 'Pago pendiente').' · '.$paymentMethodLabel)
                                : 'Sin cobrar';
                            $paymentBadgeClass = $latestPayment?->status === 'paid' ? 'paid' : ($latestPayment ? 'pending' : '');
                            $defaultAmount = number_format((($latestPayment?->amount_cents ?? $appointment->service?->price_cents ?? 0) / 100), 2, '.', '');
                            $canCloseAppointment = ! in_array($appointment->status, ['cancelled', 'canceled', 'completed'], true) && $latestPayment?->status !== 'paid';
                        @endphp
                        <article class="appointment-card {{ $appointment->trafficLightClass() }}">
                            <div class="appointment-card-time">
                                <strong>{{ $appointment->starts_at->format('g:i A') }}</strong>
                                <span>{{ $appointment->starts_at->locale('es')->isoFormat('D MMM YYYY') }}</span>
                            </div>

                            <div class="appointment-card-main">
                                <strong>
                                    @if ($appointment->source === 'google_calendar')
                                        <img class="appointment-google-badge" src="/google-g.svg" alt="" title="Importada desde Google Calendar">
                                    @endif
                                    {{ $clientName ?: 'Cliente' }} - {{ $serviceName ?: 'Cita' }}
                                </strong>
                                <span>{{ $appointment->chair_station ?: 'Sin estacion asignada' }}</span>
                            </div>

                            <div class="appointment-card-detail">
                                <b>{{ $clientPhoneLabel }}</b>
                            </div>

                            <div class="appointment-card-detail">
                                <span class="status {{ $statusClass }}">
                                    {{ $statusLabel }}
                                    @if (in_array($appointment->status, ['cancelled', 'canceled'], true))
                                        <i class="appointment-cancel-mark" title="Cita cancelada">&times;</i>
                                        @if ($appointment->client_cancelled)
                                            <i class="appointment-delivery responded" title="Cancelada por el cliente">&#10003;&#10003;</i>
                                        @endif
                                    @elseif ($appointment->client_responded)
                                        <i class="appointment-delivery responded" title="El cliente respondio">&#10003;&#10003;</i>
                                    @elseif ($appointment->notification_sent)
                                        <i class="appointment-delivery sent" title="SMS o correo enviado">&#10003;</i>
                                    @endif
                                </span>
                            </div>

                            <div class="appointment-card-detail">
                                <b>{{ $appointment->stylist?->name ?? 'Sin asignar' }}</b>
                            </div>

                            <div class="appointment-card-duration">
                                {{ $duration ? $duration.' min' : '-' }}
                            </div>

                            <div>
                                <form class="reminder-controls" method="POST" action="/citas/{{ $appointment->id }}/recordatorio">
                                    @csrf
                                    @method('PUT')
                                    <label class="reminder-toggle">
                                        <input type="hidden" name="reminder_call_enabled" value="0">
                                        <input type="checkbox" name="reminder_call_enabled" value="1" @checked($callEnabled) @disabled(! $canRemind || $voiceReminderAnswered) onchange="this.form.submit()" title="{{ $voiceReminderAnswered ? 'Cliente ya fue llamado' : '' }}">
                                        <span>Llamada</span>
                                    </label>
                                    <label class="reminder-toggle">
                                        <input type="hidden" name="reminder_sms_enabled" value="0">
                                        <input type="checkbox" name="reminder_sms_enabled" value="1" @checked($smsEnabled) @disabled(! $canRemind) onchange="this.form.submit()">
                                        <span>SMS</span>
                                    </label>
                                </form>
                                @if ($callResultLabel && ! $voiceReminderAnswered)
                                    <span class="reminder-call-result {{ $callResultClass }}">Llamada: {{ $callResultLabel }}</span>
                                @endif
                                @if ($voiceReminderAnswered)
                                    <span class="reminder-call-result reminded">Cliente ya fue llamado</span>
                                @endif
                            </div>

                            <div class="appointment-card-action">
                                <a class="btn" href="/citas/{{ $appointment->id }}/editar">Editar</a>
                                @if ($canCloseAppointment)
                                    <button class="btn primary" type="button"
                                        data-close-appointment
                                        data-action="{{ route('appointments.payments.store', $appointment) }}"
                                        data-client="{{ $clientName ?: 'Cliente' }}"
                                        data-service="{{ $serviceName ?: 'Cita' }}"
                                        data-date="{{ $appointment->starts_at->format('d/m/Y g:i A') }}"
                                        data-amount="{{ $defaultAmount }}">
                                        Cerrar cita
                                    </button>
                                @else
                                    <span class="payment-badge {{ $paymentBadgeClass }}">{{ $paymentBadge }}</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
            </div>
        @endif
    </article>

    <div class="checkout-modal" data-checkout-modal hidden>
        <div class="checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="checkout-title">
            <div class="checkout-head">
                <div>
                    <h2 id="checkout-title">Cerrar cita</h2>
                    <span data-checkout-summary>Selecciona una cita para cobrar.</span>
                </div>
                <button class="checkout-close" type="button" data-checkout-close aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" data-checkout-form>
                @csrf
                <div class="checkout-body">
                    <div class="checkout-amount">
                        <label for="checkout_amount">Total a cobrar</label>
                        <input id="checkout_amount" name="amount" type="number" min="0.01" step="0.01" required>
                    </div>
                    <div>
                        <label>Metodo de pago</label>
                        <div class="payment-methods">
                            <label class="payment-method"><input type="radio" name="method" value="cash" checked> Efectivo</label>
                            <label class="payment-method"><input type="radio" name="method" value="stripe"> Tarjeta / Stripe</label>
                            <label class="payment-method"><input type="radio" name="method" value="other"> Otro</label>
                        </div>
                    </div>
                    <div>
                        <label for="checkout_notes">Notas internas opcionales</label>
                        <textarea id="checkout_notes" name="notes" rows="3" placeholder="Referencia, transferencia, comentario de caja..."></textarea>
                    </div>
                    <input type="hidden" name="complete_appointment" value="0">
                    <label style="display:flex;align-items:center;gap:10px;margin:0;color:var(--ink);font-weight:900;">
                        <input type="checkbox" name="complete_appointment" value="1" checked style="width:auto;min-height:auto;">
                        Marcar cita como completada
                    </label>
                </div>
                <div class="checkout-actions">
                    <button class="btn" type="button" data-checkout-close>Cancelar</button>
                    <button class="btn primary" type="submit" data-checkout-submit>Registrar pago</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-checkout-modal]');
            const form = document.querySelector('[data-checkout-form]');
            const summary = document.querySelector('[data-checkout-summary]');
            const amount = document.querySelector('#checkout_amount');
            const submit = document.querySelector('[data-checkout-submit]');
            const methodInputs = Array.from(document.querySelectorAll('input[name="method"]'));

            if (!modal || !form || !summary || !amount || !submit) return;

            const updateSubmit = () => {
                const method = methodInputs.find((input) => input.checked)?.value || 'cash';
                submit.textContent = method === 'stripe' ? 'Enviar enlace Stripe' : 'Registrar pago';
            };

            document.querySelectorAll('[data-close-appointment]').forEach((button) => {
                button.addEventListener('click', () => {
                    form.action = button.dataset.action || '';
                    summary.textContent = `${button.dataset.client || 'Cliente'} · ${button.dataset.service || 'Servicio'} · ${button.dataset.date || ''}`;
                    amount.value = button.dataset.amount || '0.00';
                    form.reset();
                    amount.value = button.dataset.amount || '0.00';
                    updateSubmit();
                    modal.hidden = false;
                    amount.focus();
                });
            });

            document.querySelectorAll('[data-checkout-close]').forEach((button) => {
                button.addEventListener('click', () => { modal.hidden = true; });
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) modal.hidden = true;
            });
            methodInputs.forEach((input) => input.addEventListener('change', updateSubmit));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') modal.hidden = true;
            });
        })();
    </script>
@endsection
