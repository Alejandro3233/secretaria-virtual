@extends('layouts.app')

@section('title', 'Consola - Secretaria Virtual')
@section('page_title', 'Consola principal')
@section('page_subtitle', auth()->user()->name.' - '.($clinic?->name ?? 'Salon sin configurar'))
@section('page_actions')
    <a class="btn" href="/agenda">Ver agenda</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    @php
        $liveClientName = $liveCall
            ? trim(($liveCall->first_name ?? '').' '.($liveCall->last_name ?? ''))
            : '';
        $liveClientName = $liveClientName !== '' ? $liveClientName : ($liveCall->from_phone ?? $liveCall->recipient ?? 'Cliente');
        $liveInitials = collect(explode(' ', $liveClientName))
            ->filter()
            ->take(2)
            ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))
            ->implode('');
        $liveStartedAt = $liveCall ? \Illuminate\Support\Carbon::parse($liveCall->created_at)->timezone($timezone) : null;
        $liveMinutes = $liveStartedAt ? max(0, $liveStartedAt->diffInMinutes($now)) : 0;
        $liveStatusLabel = match ($liveCall->status ?? null) {
            'queued' => 'En cola',
            'initiated' => 'Iniciando',
            'ringing' => 'Sonando',
            'in-progress', 'answered', 'received' => 'Llamada en curso',
            default => $liveCall ? 'Actividad reciente' : 'Sin llamada en curso',
        };
        $liveMessage = data_get($liveCall, 'transcript')
            ?: data_get($liveCall, 'body')
            ?: 'La secretaria esta gestionando esta llamada y actualizara la agenda si hay una accion confirmada.';
        $liveAppointmentTime = $liveCall?->appointment_starts_at
            ? \Illuminate\Support\Carbon::parse($liveCall->appointment_starts_at)->timezone($timezone)
            : null;
        $nextReminderCallAt = $nextReminderCall?->getAttribute('scheduled_call_at');
        $nextReminderClient = $nextReminderCall?->client
            ? trim(($nextReminderCall->client->first_name ?? '').' '.($nextReminderCall->client->last_name ?? ''))
            : '';
        $nextReminderClient = $nextReminderClient !== '' ? $nextReminderClient : 'Cliente pendiente';
        $nextReminderStatus = $nextReminderCall?->getAttribute('reminder_call_status');
        $nextReminderLabel = match ($nextReminderStatus) {
            'in-progress', 'answered', 'ringing', 'initiated' => 'Llamada activa',
            'sent', 'completed' => 'Contestó',
            'no-answer' => 'No contestó',
            'busy' => 'Ocupado',
            'failed', 'canceled' => 'Fallida',
            default => $nextReminderCall ? 'Llamada en cola' : 'Libre',
        };
        $nextReminderStatusClass = match ($nextReminderStatus) {
            'in-progress', 'answered', 'ringing', 'initiated' => 'info',
            'sent', 'completed' => 'ok',
            'no-answer', 'busy', 'failed', 'canceled' => 'danger',
            default => 'ok',
        };
        $nextReminderMessage = $nextReminderCall?->getAttribute('reminder_call_message');
        $lastOperationalActivity = collect([
            $latestInboundCalls->first(),
            $latestCalls->first(),
            $smsDetails->first(),
            $emailDetails->first(),
        ])->filter()->sortByDesc(fn ($activity) => \Illuminate\Support\Carbon::parse($activity->created_at)->timestamp)->first();
        $lastOperationalAt = $lastOperationalActivity
            ? \Illuminate\Support\Carbon::parse($lastOperationalActivity->sent_at ?? $lastOperationalActivity->created_at)->timezone($timezone)
            : null;
        $lastOperationalClient = $lastOperationalActivity
            ? trim(($lastOperationalActivity->first_name ?? '').' '.($lastOperationalActivity->last_name ?? ''))
            : '';
        $lastOperationalClient = $lastOperationalClient ?: ($lastOperationalActivity->recipient ?? $lastOperationalActivity->from_phone ?? 'Cliente');
        $lastOperationalEvent = match ($lastOperationalActivity->event ?? $lastOperationalActivity->intent ?? null) {
            'appointment_created' => 'Cita creada',
            'appointment_updated' => 'Cita actualizada',
            'appointment_reconfirmed' => 'Cita reconfirmada',
            'appointment_reminder_call' => 'Recordatorio por llamada',
            'appointment_reminder_sms' => 'Recordatorio por SMS',
            'appointment_reschedule_link' => 'Enlace para reagendar enviado',
            'appointment_client_response' => 'Respuesta del cliente recibida',
            default => $lastOperationalActivity ? 'Actividad registrada' : 'Sin actividad registrada',
        };
        $futureAppointmentsCount = $todayAppointments->filter(fn ($appointment) => $appointment->starts_at->greaterThanOrEqualTo($now))->count();
        $nextActionsCount = collect([$scheduleOptimization, $nextAppointment])->filter()->count()
            + $scheduleOptimizationHistory->count()
            + $reminderCallAppointments->count();
        $noraNextClient = $nextAppointment
            ? (trim(($nextAppointment->client?->first_name ?? '').' '.($nextAppointment->client?->last_name ?? '')) ?: 'el cliente')
            : null;
        $noraVoiceResponses = [
            'appointments' => 'En el período seleccionado hay '.$todayAppointments->count().' citas.',
            'next' => $nextAppointment
                ? 'La próxima cita es a las '.$nextAppointment->starts_at->format('g:i A').' con '.$noraNextClient.', para '.($nextAppointment->service?->name ?? 'un servicio por definir').'.'
                : 'No hay una próxima cita en el período seleccionado.',
            'revenue' => 'Las ganancias previstas del período seleccionado son '.number_format($expectedRevenueCents / 100, 2).' dólares.',
            'optimization' => $scheduleOptimization
                ? 'Podemos proponer a '.(trim(($scheduleOptimization['appointment']->client?->first_name ?? '').' '.($scheduleOptimization['appointment']->client?->last_name ?? '')) ?: 'un cliente').' adelantar su cita de las '.$scheduleOptimization['current_start']->format('g:i A').' a las '.$scheduleOptimization['proposed_start']->format('g:i A').'.'
                : 'Ahora mismo no he encontrado una cita que podamos adelantar en el período seleccionado.',
            'calls' => $reminderCallAppointments->count() > 0
                ? 'Hay '.$reminderCallAppointments->count().' llamadas de recordatorio que puedes adelantar desde Próxima acción.'
                : 'No hay llamadas de recordatorio pendientes para adelantar en el período seleccionado.',
            'help' => 'Puedes pedirme el resumen del día, la próxima cita, cuántas citas hay, las ganancias previstas, los huecos de la agenda o las llamadas pendientes.',
        ];
    @endphp

    <style>
        .console-shell {
            display: grid;
            gap: 18px;
        }

        .optimization-card { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 16px; align-items: center; border: 1px solid #bfdbfe; border-left: 5px solid #2563eb; background: #eff6ff; }
        .optimization-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 50%; background: #dbeafe; color: #1d4ed8; font-size: 23px; font-weight: 900; }
        .optimization-copy b { display: block; font-size: 17px; }
        .optimization-copy span { display: block; margin-top: 5px; color: #475569; line-height: 1.45; }
        .optimization-actions { text-align: right; }
        .optimization-actions small { display: block; margin-top: 7px; color: #64748b; }
        .next-action-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .next-action-nav { display: inline-flex; gap: 5px; }
        .next-action-nav button { width: 25px; height: 25px; display: grid; place-items: center; border: 1px solid #bbd9c7; border-radius: 50%; background: white; color: #166534; font-size: 17px; font-weight: 900; cursor: pointer; }
        .next-action-slide[hidden] { display: none; }
        .next-action-slide { display: grid; gap: 4px; }
        .next-action-slide form { margin-top: 6px; }
        .next-action-slide .btn { min-height: 30px; padding: 0 10px; font-size: 11px; }

        .console-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 285px;
            gap: 18px;
            align-items: stretch;
        }

        .live-call-card {
            min-height: 420px;
            display: grid;
            align-content: start;
            gap: 18px;
            border: 0;
            padding: 22px;
            background:
                radial-gradient(circle at 100% 0%, rgba(192, 38, 90, .18), transparent 34%),
                linear-gradient(135deg, #ffffff 0%, #fff8fb 58%, #f8fbff 100%);
            box-shadow: 0 18px 44px rgba(24, 18, 22, .08);
        }

        .live-call-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .live-head-actions { display: flex; align-items: center; justify-content: flex-end; gap: 10px; }
        .nora-listening-button { min-height: 32px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #ead7e0; border-radius: 999px; padding: 0 10px; background: rgba(255,255,255,.72); color: #66545c; font-size: 11px; font-weight: 900; cursor: pointer; }
        .nora-listening-button:hover { border-color: #d8b7c6; background: white; }
        .nora-listening-button .icon { width: 14px; height: 14px; }
        .nora-listening-button .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #a8a1a5; box-shadow: 0 0 0 3px rgba(168,161,165,.14); }
        .nora-listening-button.is-listening { color: #166534; border-color: #bbf7d0; background: #f0fdf4; }
        .nora-listening-button.is-listening .status-dot { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); animation: nora-listening-pulse 1.5s infinite; }
        .nora-listening-button.is-paused { color: #92400e; border-color: #fde68a; background: #fffbeb; }
        .nora-listening-button.is-speaking { color: var(--brand); border-color: #f3bfd3; background: #fff1f6; }
        @keyframes nora-listening-pulse { 50% { box-shadow: 0 0 0 6px rgba(34,197,94,.08); } }

        .live-call-state {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #245b41;
            font-weight: 900;
        }

        .live-dot {
            width: 18px;
            height: 18px;
            display: inline-grid;
            place-items: center;
            border-radius: 50%;
            background: #d8f5e4;
        }

        .live-dot::after {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #41b77a;
            animation: livePulse 1.8s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% { transform: scale(.8); opacity: .65; box-shadow: 0 0 0 0 rgba(65, 183, 122, .35); }
            50% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 7px rgba(65, 183, 122, 0); }
        }

        .live-call-time {
            color: var(--muted);
            font-weight: 900;
            text-align: right;
        }

        .live-call-time b,
        .live-call-time small { display: block; }
        .live-call-time b { color: var(--ink); font-size: 15px; }
        .live-call-time small { margin-top: 2px; font-size: 11px; }

        .hot-call-alert { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; gap: 12px; align-items: center; padding: 12px 14px; border: 1px solid #f9a8d4; border-radius: 8px; background: #fff1f6; box-shadow: 0 8px 22px rgba(192, 38, 90, .12); }
        .hot-call-alert[hidden] { display: none; }
        .hot-call-ring { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; background: var(--brand); color: white; font-size: 14px; animation: hotCallRing 1.2s ease-in-out infinite; }
        .hot-call-copy b, .hot-call-copy span { display: block; }
        .hot-call-copy b { color: var(--ink); font-size: 13px; }
        .hot-call-copy span { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .hot-call-badge { color: var(--brand); font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .hot-call-actions { display: flex; gap: 8px; }
        .hot-call-button { border: 0; border-radius: 7px; padding: 9px 12px; color: white; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
        .hot-call-button:disabled { cursor: wait; opacity: .5; }
        .hot-call-button.answer { background: #15803d; }
        .hot-call-button.end { background: #b91c1c; }
        .hot-call-button.assistant { background: var(--brand); }
        @keyframes hotCallRing { 0%, 100% { transform: rotate(-5deg) scale(.96); } 50% { transform: rotate(5deg) scale(1.06); } }

        .live-call-panel {
            display: grid;
            gap: 14px;
            border-radius: 8px;
            padding: 24px;
            background: rgba(255, 255, 255, .9);
        }

        .live-client {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            padding-bottom: 16px;
            border-bottom: 1px solid #eadde4;
        }

        .live-avatar {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--brand);
            color: white;
            font-weight: 900;
        }

        .live-client b,
        .live-client span,
        .appointment-card b,
        .appointment-card span,
        .call-row b,
        .call-row span {
            display: block;
        }

        .live-client span,
        .appointment-card span,
        .call-row span {
            margin-top: 4px;
            color: var(--muted);
        }

        .call-bubble {
            max-width: 84%;
            border-radius: 8px;
            padding: 13px 15px;
            background: #f1eef0;
            line-height: 1.45;
        }

        .call-bubble.assistant {
            justify-self: end;
            background: var(--brand);
            color: white;
            font-weight: 800;
        }

        .call-bubble.assistant span {
            color: rgba(255, 255, 255, .84);
            font-weight: 700;
        }

        .live-appointment {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            border: 1px solid #c9e8d3;
            border-radius: 8px;
            padding: 16px;
            background: #f5fbf7;
        }

        .live-appointment-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
            margin-top: 14px;
        }

        .system-watch { display: grid; gap: 12px; }
        .system-watch-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .system-watch-item { min-width: 0; padding: 13px; border: 1px solid #ede2e8; border-radius: 8px; background: #fffafd; }
        .system-watch-item b, .system-watch-item span { display: block; }
        .system-watch-item b { color: var(--ink); font-size: 13px; }
        .system-watch-item span { margin-top: 5px; color: var(--muted); font-size: 12px; line-height: 1.35; }
        .revenue-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .revenue-nav { display: inline-flex; gap: 5px; }
        .revenue-nav button { width: 24px; height: 24px; display: grid; place-items: center; border: 1px solid #e3cbd7; border-radius: 50%; background: white; color: var(--brand); font-size: 16px; font-weight: 900; cursor: pointer; }
        .revenue-slide[hidden] { display: none; }
        .revenue-slide { min-height: 43px; }
        .system-watch-label { margin: 0 0 7px !important; color: var(--brand) !important; font-size: 10px !important; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
        .system-feed { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .system-feed-card { padding: 14px; border-radius: 8px; background: #f1eef0; }
        .system-feed-card.next { border: 1px solid #c9e8d3; background: #f5fbf7; }
        .system-feed-card b, .system-feed-card span { display: block; }
        .system-feed-card b { color: var(--ink); font-size: 13px; }
        .system-feed-card span { margin-top: 5px; color: var(--muted); font-size: 12px; line-height: 1.4; }
        .system-feed-time { color: var(--brand) !important; font-weight: 900; }

        .console-side {
            display: grid;
            gap: 14px;
        }

        .date-nav-card {
            display: grid;
            gap: 10px;
        }

        .date-nav-card b,
        .date-nav-card span {
            display: block;
        }

        .date-nav-card span {
            color: var(--muted);
        }

        .console-kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .kpi-card {
            min-height: 82px;
            display: grid;
            align-content: space-between;
            padding: 10px;
            width: 100%;
            color: inherit;
            font: inherit;
            text-align: left;
            cursor: pointer;
        }

        .kpi-card .metric-label { font-size: 11px; line-height: 1.2; }
        .kpi-card .trend { margin-top: 3px; font-size: 10px; line-height: 1.25; }

        .kpi-card:hover,
        .kpi-card.active {
            border-color: #d7c4ce;
            box-shadow: 0 10px 24px rgba(24, 18, 22, .08);
        }

        .kpi-card.active {
            outline: 2px solid rgba(192, 38, 90, .12);
        }

        .kpi-value {
            margin-top: 3px;
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
        }

        .console-main {
            display: block;
        }

        .appointment-list,
        .call-list {
            display: grid;
            gap: 10px;
        }

        .appointment-card,
        .call-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            border: 1px solid #f2eaf0;
            border-radius: 8px;
            padding: 12px;
            background: #fffafd;
        }

        .appointment-time {
            color: var(--brand);
            font-size: 16px;
            font-weight: 900;
            white-space: nowrap;
        }

        .empty-state {
            border: 1px dashed var(--line);
            border-radius: 8px;
            padding: 18px;
            color: var(--muted);
            font-weight: 800;
            background: white;
        }

        .call-status-text {
            display: inline-flex;
            align-items: center;
            color: var(--green);
            font-size: 12px;
            font-weight: 900;
        }

        .activity-card {
            border: 1px solid var(--line);
            border-left: 5px solid #ca8a04;
            border-radius: 10px;
            padding: 16px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(24, 18, 22, .05);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .activity-card:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(24, 18, 22, .09); }
        .activity-card--ok { border-left-color: #15803d; }
        .activity-card--info { border-left-color: #2563eb; }
        .activity-card--danger { border-left-color: #dc2626; }
        .activity-card--wait { border-left-color: #ca8a04; }
        .activity-card-head { display: grid; grid-template-columns: 115px minmax(0, 1fr) auto; gap: 16px; align-items: center; }
        .activity-time strong, .activity-title strong { display: block; color: var(--ink); font-size: 15px; font-weight: 900; }
        .activity-time strong { color: var(--brand); font-size: 17px; }
        .activity-time span, .activity-title span, .activity-info-block > span { display: block; margin-top: 4px; color: var(--muted); font-size: 13px; }
        .activity-title .activity-handler { font-weight: 900; }
        .activity-title .activity-handler--salon { color: #15803d; }
        .activity-title .activity-handler--nora { color: var(--brand); }
        .activity-title .activity-handler--pending { color: #b45309; }
        .activity-info-block > span { max-width: 100%; overflow-wrap: anywhere; word-break: break-word; }
        .activity-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; margin-top: 14px; }
        .activity-info-block { min-width: 0; padding: 12px; border: 1px solid #f0e5eb; border-radius: 8px; background: #fffafd; }
        .activity-info-block b { display: block; overflow-wrap: anywhere; color: var(--ink); font-size: 13px; }
        .activity-info-block.muted-block { background: #f8fafc; }
        .activity-label { display: block; margin: 0 0 6px !important; color: var(--brand) !important; font-size: 10px !important; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
        .activity-message { margin-top: 10px; padding: 13px 14px; border-radius: 8px; background: #f1eef0; }
        .activity-message p { margin: 0; color: var(--ink); line-height: 1.5; white-space: pre-wrap; }
        .response-block--ok { border-color: #bbf7d0; background: #f0fdf4; }
        .response-block--ok .activity-label { color: #15803d !important; }
        .response-block--info { border-color: #bfdbfe; background: #eff6ff; }
        .response-block--info .activity-label { color: #1d4ed8 !important; }
        .response-block--danger { border-color: #fecaca; background: #fef2f2; }
        .response-block--danger .activity-label { color: #b91c1c !important; }
        .response-block--wait { border-color: #fde68a; background: #fffbeb; }
        .activity-more { margin-top: 10px; border-top: 1px solid #eadde4; }
        .activity-more summary { padding-top: 12px; color: var(--brand); cursor: pointer; font-size: 13px; font-weight: 900; }
        .activity-more-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 12px; }
        .activity-more-grid section { padding: 13px; border-radius: 8px; background: #fffafd; }
        .activity-more-grid h3 { margin: 0 0 8px; font-size: 13px; }
        .activity-more-grid p { margin: 6px 0; color: var(--muted); font-size: 12px; line-height: 1.45; overflow-wrap: anywhere; }
        .activity-more-grid p b { color: var(--ink); }
        .activity-more-grid .btn { margin-top: 6px; }
        .activity-error { color: #991b1b !important; }

        .console-detail-panel {
            display: none;
        }

        .console-detail-panel.active {
            display: block;
        }

        @media (max-width: 1040px) {
            .console-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .hot-call-alert { grid-template-columns: auto minmax(0, 1fr); }
            .hot-call-actions { grid-column: 1 / -1; }
            .hot-call-badge { display: none; }
            .live-call-card,
            .live-call-panel {
                padding: 16px;
            }

            .console-kpis,
            .live-appointment-grid,
            .appointment-card,
            .call-row,
            .system-watch-grid,
            .system-feed {
                grid-template-columns: 1fr;
            }

            .call-bubble {
                max-width: 100%;
            }

            .activity-card-head,
            .activity-info-grid,
            .activity-more-grid { grid-template-columns: 1fr; }
            .optimization-card { grid-template-columns: 1fr; }
            .optimization-actions { text-align: left; }
        }
    </style>

    <div class="console-shell">
        @if (session('optimization_status'))
            <div class="card" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">{{ session('optimization_status') }}</div>
        @endif
        @if (session('optimization_error'))
            <div class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
                {{ session('optimization_error') }}
                @if ($scheduleOptimization)
                    Hemos encontrado otra opción disponible; <a href="#next-action-panel" style="color:inherit;text-decoration:underline;">puedes revisarla en “Próxima acción”</a>.
                @else
                    No encontramos otro hueco compatible para ese día.
                @endif
            </div>
        @endif
        @if (session('call_status'))
            <div class="card" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">{{ session('call_status') }}</div>
        @endif
        @if (session('call_error'))
            <div class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">{{ session('call_error') }}</div>
        @endif

        <section class="console-hero" aria-label="Vista rapida del negocio">
            <article class="card live-call-card">
                <div class="live-call-head">
                    <span class="live-call-state"><span class="live-dot"></span>{{ $liveCall ? $liveStatusLabel : 'Sistema activo · supervisando' }}</span>
                    <div class="live-head-actions">
                        <button class="nora-listening-button" type="button" data-nora-listening aria-pressed="false" title="Nora escucha órdenes mientras esta consola permanezca abierta">
                            <span class="status-dot" aria-hidden="true"></span>
                            <svg class="icon" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/></svg>
                            <span data-nora-listening-label>Activar Nora</span>
                        </button>
                        <span class="live-call-time">
                            @if ($liveCall)
                                {{ $clinic?->name ?? 'Salón sin configurar' }}
                            @else
                                <b data-live-clock>{{ $now->format('H:i:s') }}</b>
                                <small>{{ $clinic?->name ?? 'Salón sin configurar' }}</small>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="hot-call-alert" data-hot-call-alert @if (! $liveCall) hidden @endif aria-live="assertive">
                    <span class="hot-call-ring">☎</span>
                    <div class="hot-call-copy">
                        <b data-hot-call-title>{{ $liveCall ? $liveStatusLabel : 'Llamada detectada en tiempo real' }}</b>
                        <span data-hot-call-client>{{ $liveCall ? $liveClientName : 'Preparando información de la llamada…' }}</span>
                    </div>
                    <span class="hot-call-badge">En vivo</span>
                    <div class="hot-call-actions" data-call-actions>
                        <button class="hot-call-button answer" type="button" data-call-answer disabled>Contestar</button>
                        <button class="hot-call-button assistant" type="button" data-call-assistant disabled>Pasar a Nora</button>
                        <button class="hot-call-button end" type="button" data-call-end hidden>Colgar</button>
                    </div>
                </div>

                <div class="live-call-panel">
                    @if ($liveCall)
                        <div class="live-client">
                            <div class="live-avatar">{{ $liveInitials ?: 'CL' }}</div>
                            <div>
                                <b>{{ $liveClientName }}</b>
                                <span>{{ ($liveCall->channel ?? null) === 'voice' ? 'Llamada saliente' : 'Cliente en linea' }}</span>
                            </div>
                        </div>

                        <div class="call-bubble">{{ \Illuminate\Support\Str::limit($liveMessage, 180) }}</div>
                        <div class="call-bubble assistant">
                            La secretaria esta atendiendo la solicitud.
                            <span>Las citas y avisos se actualizan cuando la accion queda registrada.</span>
                        </div>

                        <div class="live-appointment">
                            <div>
                                <b>{{ $liveAppointmentTime ? 'Cita relacionada' : 'Seguimiento de llamada' }}</b>
                                <div class="live-appointment-grid">
                                    <span>{{ $liveAppointmentTime ? $liveAppointmentTime->isoFormat('dddd, HH:mm') : 'Sin cita asociada todavia' }}</span>
                                    <span>{{ $liveCall->service_name ?? 'Servicio por definir' }}</span>
                                    <span>{{ $liveCall->stylist_name ? 'Con '.$liveCall->stylist_name : 'Profesional por asignar' }}</span>
                                    <span>{{ $liveCall->duration_minutes ? $liveCall->duration_minutes.' minutos' : 'Duracion pendiente' }}</span>
                                </div>
                            </div>
                            <span class="status {{ ($liveCall->appointment_status ?? null) === 'confirmed' ? 'ok' : 'wait' }}">
                                {{ $liveCall->appointment_status ? ucfirst($liveCall->appointment_status) : 'En revision' }}
                            </span>
                        </div>
                    @else
                        <div class="live-client">
                            <div class="live-avatar">SV</div>
                            <div>
                                <b>Nora está disponible</b>
                                <span>No hay llamadas activas. La línea, la agenda y los avisos siguen bajo vigilancia.</span>
                            </div>
                        </div>

                        <div class="system-watch">
                            <div class="system-watch-grid">
                                <div class="system-watch-item">
                                    <span class="system-watch-label">Telefonía</span>
                                    <b>Línea preparada</b>
                                    <span>{{ $activeCallsCount ? $activeCallsCount.' llamada(s) detectada(s)' : 'Esperando la próxima llamada' }}</span>
                                </div>
                                <div class="system-watch-item">
                                    <span class="system-watch-label">Agenda vigilada</span>
                                    <b>{{ $todayAppointments->count() }} cita(s) en el período</b>
                                    <span>{{ $futureAppointmentsCount }} todavía por atender</span>
                                </div>
                                <div class="system-watch-item">
                                    <div class="revenue-head">
                                        <span class="system-watch-label">Ganancias previstas</span>
                                        @if ($employeeRevenueBreakdown->isNotEmpty())
                                            <div class="revenue-nav" aria-label="Ver ganancias por empleado">
                                                <button type="button" data-revenue-prev aria-label="Empleado anterior">‹</button>
                                                <button type="button" data-revenue-next aria-label="Empleado siguiente">›</button>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="revenue-slide" data-revenue-slide>
                                        <b>${{ number_format($expectedRevenueCents / 100, 2) }}</b>
                                        <span>Total de {{ $pricedAppointmentsCount }} servicio(s) del {{ $selectedRange === 'day' ? 'día' : 'período' }}</span>
                                    </div>
                                    @foreach ($employeeRevenueBreakdown as $employeeRevenue)
                                        <div class="revenue-slide" data-revenue-slide hidden>
                                            <b>${{ number_format($employeeRevenue['revenue_cents'] / 100, 2) }}</b>
                                            <span>{{ $employeeRevenue['name'] }} · {{ $employeeRevenue['services_count'] }} servicio(s)</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="system-feed">
                                <div class="system-feed-card">
                                    <span class="system-watch-label">Última actividad real</span>
                                    <b>{{ $lastOperationalEvent }}</b>
                                    @if ($lastOperationalAt)
                                        <span>{{ $lastOperationalClient }}</span>
                                        <span class="system-feed-time">{{ $lastOperationalAt->diffForHumans() }} · {{ $lastOperationalAt->format('g:i A') }}</span>
                                    @else
                                        <span>No hay movimientos en {{ $selectedDateLabel }}.</span>
                                    @endif
                                </div>
                                <div class="system-feed-card next" id="next-action-panel">
                                    <div class="next-action-head">
                                        <span class="system-watch-label">Próxima acción</span>
                                        @if ($nextActionsCount > 1)
                                            <div class="next-action-nav" aria-label="Cambiar próxima acción">
                                                <button type="button" data-next-action-prev aria-label="Acción anterior">‹</button>
                                                <button type="button" data-next-action-next aria-label="Acción siguiente">›</button>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($scheduleOptimization)
                                        @php
                                            $optimizationAppointment = $scheduleOptimization['appointment'];
                                            $optimizationClient = trim(($optimizationAppointment->client?->first_name ?? '').' '.($optimizationAppointment->client?->last_name ?? '')) ?: 'el cliente';
                                        @endphp
                                        <div class="next-action-slide" data-next-action-slide data-action-type="optimization">
                                            @if (session('optimization_error'))
                                                <span class="status info" style="width:max-content;">Nueva opción encontrada</span>
                                            @endif
                                            <b>Podemos completar la agenda más temprano</b>
                                            <span>Proponer a {{ $optimizationClient }} cambiar de {{ $scheduleOptimization['current_start']->format('g:i A') }} a {{ $scheduleOptimization['proposed_start']->format('g:i A') }}.</span>
                                            <span class="system-feed-time">Con {{ $scheduleOptimization['stylist']->name }}</span>
                                            <form method="POST" action="{{ route('schedule-optimization.send', $optimizationAppointment) }}">
                                                @csrf
                                                <input type="hidden" name="proposed_start" value="{{ $scheduleOptimization['proposed_start']->toIso8601String() }}">
                                                <input type="hidden" name="proposed_stylist_id" value="{{ $scheduleOptimization['stylist']->id }}">
                                                <button class="btn primary" type="submit" @disabled($scheduleOptimization['already_sent'])>
                                                    {{ $scheduleOptimization['already_sent'] ? 'Propuesta enviada' : 'Enviar propuesta por SMS' }}
                                                </button>
                                            </form>
                                        </div>
                                    @endif

                                    @foreach ($scheduleOptimizationHistory as $previousOptimization)
                                        <div class="next-action-slide" data-next-action-slide data-action-type="optimization-history" hidden>
                                            <span class="status {{ $previousOptimization['accepted'] ? 'ok' : 'info' }}" style="width:max-content;">
                                                {{ $previousOptimization['accepted'] ? 'Propuesta aceptada' : 'Propuesta enviada' }}
                                            </span>
                                            <b>{{ $previousOptimization['client_name'] }}</b>
                                            <span>
                                                Cambio propuesto de {{ $previousOptimization['original_start']->format('g:i A') }}
                                                a {{ $previousOptimization['proposed_start']->format('g:i A') }}.
                                            </span>
                                            <span class="system-feed-time">
                                                {{ $previousOptimization['stylist'] ? 'Con '.$previousOptimization['stylist']->name : 'Profesional por asignar' }}
                                                · enviado {{ $previousOptimization['sent_at']->diffForHumans() }}
                                            </span>
                                        </div>
                                    @endforeach

                                    @foreach ($reminderCallAppointments as $reminderAppointment)
                                        @php
                                            $reminderClient = trim(($reminderAppointment->client?->first_name ?? '').' '.($reminderAppointment->client?->last_name ?? '')) ?: 'Cliente pendiente';
                                            $reminderAt = $reminderAppointment->getAttribute('scheduled_call_at');
                                            $manualCallActive = in_array($reminderAppointment->getAttribute('reminder_call_status'), ['queued', 'initiated', 'ringing', 'in-progress'], true);
                                        @endphp
                                        <div class="next-action-slide" data-next-action-slide @if ($scheduleOptimization || $scheduleOptimizationHistory->isNotEmpty() || ! $loop->first) hidden @endif>
                                            <b>Llamar a {{ $reminderClient }}</b>
                                            <span class="system-feed-time">{{ $reminderAt?->isPast() ? 'Lista para llamar' : 'Programada a las '.$reminderAt?->format('g:i A') }}</span>
                                            <span>Cita a las {{ $reminderAppointment->starts_at->format('g:i A') }}</span>
                                            <form method="POST" action="{{ route('appointments.call-now', $reminderAppointment) }}">
                                                @csrf
                                                <button class="btn primary" type="submit" @disabled($manualCallActive)>
                                                    {{ $manualCallActive ? 'Llamada en proceso' : 'Llamar ahora con Nora' }}
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach

                                    @if ($nextAppointment)
                                        <div class="next-action-slide" data-next-action-slide @if ($scheduleOptimization || $scheduleOptimizationHistory->isNotEmpty() || $reminderCallAppointments->isNotEmpty()) hidden @endif>
                                            <b>Próxima cita: {{ trim(($nextAppointment->client?->first_name ?? '').' '.($nextAppointment->client?->last_name ?? '')) ?: 'Cliente' }}</b>
                                            <span class="system-feed-time">{{ $nextAppointment->starts_at->format('g:i A') }}</span>
                                            <span>{{ $nextAppointment->service?->name ?? 'Servicio por definir' }}</span>
                                        </div>
                                    @endif

                                    @if ($nextActionsCount === 0)
                                        <div class="next-action-slide" data-next-action-slide>
                                            <b>Disponible para nuevas reservas</b>
                                            <span>No hay acciones automáticas pendientes en este período.</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                    @endif
                </div>
            </article>

            <div class="console-side">
                <article class="card date-nav-card">
                    <section class="mini-calendar" aria-label="Mini calendario">
                        <div class="mini-calendar-head">
                            <b>{{ ucfirst($selectedDay->copy()->locale('es')->isoFormat('MMMM [de] YYYY')) }}</b>
                            <div>
                                <a href="{{ $calendarPreviousMonthUrl }}" aria-label="Mes anterior">&lt;</a>
                                <a href="{{ $calendarNextMonthUrl }}" aria-label="Mes siguiente">&gt;</a>
                            </div>
                        </div>
                        <div class="mini-calendar-grid">
                            @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $weekday)
                                <span class="mini-weekday">{{ $weekday }}</span>
                            @endforeach
                            @foreach ($miniCalendarDays as $miniDay)
                                @php
                                    $miniDayClasses = collect([
                                        ! $miniDay->isSameMonth($selectedDay) ? 'is-muted' : null,
                                        $miniDay->isToday() ? 'is-today' : null,
                                        $selectedRange === 'day' && $miniDay->isSameDay($selectedDay) ? 'is-selected-week' : null,
                                        $selectedRange !== 'day' && $miniDay->betweenIncluded($rangeStart, $rangeEnd) ? 'is-selected-week' : null,
                                    ])->filter()->implode(' ');
                                    $miniDayUrl = url('/consola').'?'.http_build_query([
                                        'date' => $miniDay->toDateString(),
                                        'view' => $selectedRange,
                                    ]);
                                @endphp
                                <a class="{{ $miniDayClasses }}" href="{{ $miniDayUrl }}">{{ $miniDay->format('j') }}</a>
                            @endforeach
                        </div>
                    </section>
                </article>

                <section class="console-kpis" aria-label="Indicadores principales">
                    <button class="card kpi-card" type="button" data-console-filter="llamadas-realizadas">
                        <div class="metric-label">Llamadas realizadas</div>
                        <div class="kpi-value">{{ $outboundCallsToday }}</div>
                        <div class="trend">Recordatorios de voz</div>
                    </button>
                    <button class="card kpi-card active" type="button" data-console-filter="llamadas-recibidas">
                        <div class="metric-label">Llamadas recibidas</div>
                        <div class="kpi-value">{{ $callsToday }}</div>
                        <div class="trend">Ver detalles y estados</div>
                    </button>
                    <button class="card kpi-card" type="button" data-console-filter="sms-enviados">
                        <div class="metric-label">SMS enviados</div>
                        <div class="kpi-value">{{ $smsToday }}</div>
                        <div class="trend">Confirmaciones y avisos</div>
                    </button>
                    <button class="card kpi-card" type="button" data-console-filter="correos-enviados">
                        <div class="metric-label">Correos enviados</div>
                        <div class="kpi-value">{{ $emailsToday }}</div>
                        <div class="trend">Emails registrados</div>
                    </button>
                </section>
            </div>
        </section>

        <section class="console-main" aria-label="Agenda y actividad">
            <article class="card">
                <div class="section-title">
                    <div>
                        <h2 id="console-detail-title">Llamadas recibidas</h2>
                        <span class="subtitle" id="console-detail-subtitle">{{ $selectedDateLabel }}</span>
                    </div>
                    <a class="btn" href="/agenda">Agenda</a>
                </div>

                <div class="console-detail-panel" data-console-panel="llamadas-realizadas" data-title="Llamadas realizadas" data-subtitle="Recordatorios de voz enviados en {{ $selectedDateLabel }}">
                    <div class="call-list">
                        @forelse ($latestCalls as $call)
                            @include('console.partials.activity-card', ['item' => $call, 'kind' => 'outbound'])
                        @empty
                            <div class="empty-state">Todavia no se han realizado llamadas de recordatorio en esta fecha.</div>
                        @endforelse
                    </div>
                </div>

                <div class="console-detail-panel active" data-console-panel="llamadas-recibidas" data-title="Llamadas recibidas" data-subtitle="Entrantes gestionadas por la secretaria en {{ $selectedDateLabel }}">
                    <div class="call-list">
                        @forelse ($latestInboundCalls as $call)
                            @include('console.partials.activity-card', ['item' => $call, 'kind' => 'inbound'])
                        @empty
                            <div class="empty-state">Aun no hay llamadas entrantes registradas en esta fecha.</div>
                        @endforelse
                    </div>
                </div>

                <div class="console-detail-panel" data-console-panel="sms-enviados" data-title="SMS enviados" data-subtitle="Mensajes registrados en {{ $selectedDateLabel }}">
                    <div class="call-list">
                        @forelse ($smsDetails as $message)
                            @include('console.partials.activity-card', ['item' => $message, 'kind' => 'sms'])
                        @empty
                            <div class="empty-state">No se han enviado SMS en esta fecha.</div>
                        @endforelse
                    </div>
                </div>

                <div class="console-detail-panel" data-console-panel="correos-enviados" data-title="Correos enviados" data-subtitle="Emails registrados en {{ $selectedDateLabel }}">
                    <div class="call-list">
                        @forelse ($emailDetails as $message)
                            @include('console.partials.activity-card', ['item' => $message, 'kind' => 'email'])
                        @empty
                            <div class="empty-state">No se han enviado correos en esta fecha.</div>
                        @endforelse
                    </div>
                </div>
            </article>
        </section>
    </div>

    <script>
        const consoleFilterCards = Array.from(document.querySelectorAll('[data-console-filter]'));
        const consoleDetailPanels = Array.from(document.querySelectorAll('[data-console-panel]'));
        const consoleDetailTitle = document.getElementById('console-detail-title');
        const consoleDetailSubtitle = document.getElementById('console-detail-subtitle');

        function showConsoleDetail(panelName) {
            consoleFilterCards.forEach((card) => {
                card.classList.toggle('active', card.dataset.consoleFilter === panelName);
            });

            consoleDetailPanels.forEach((panel) => {
                const isActive = panel.dataset.consolePanel === panelName;
                panel.classList.toggle('active', isActive);

                if (isActive) {
                    if (consoleDetailTitle) {
                        consoleDetailTitle.textContent = panel.dataset.title || 'Detalle';
                    }

                    if (consoleDetailSubtitle) {
                        consoleDetailSubtitle.textContent = panel.dataset.subtitle || '';
                    }
                }
            });
        }

        consoleFilterCards.forEach((card) => {
            card.addEventListener('click', () => showConsoleDetail(card.dataset.consoleFilter));
        });

        const liveClock = document.querySelector('[data-live-clock]');
        if (liveClock) {
            const clockFormatter = new Intl.DateTimeFormat('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
                timeZone: @json($timezone),
            });
            const updateLiveClock = () => { liveClock.textContent = clockFormatter.format(new Date()); };
            updateLiveClock();
            window.setInterval(updateLiveClock, 1000);
        }

        const nextActionSlides = Array.from(document.querySelectorAll('[data-next-action-slide]'));
        const nextActionPrevious = document.querySelector('[data-next-action-prev]');
        const nextActionNext = document.querySelector('[data-next-action-next]');
        let activeNextAction = 0;

        const showNextAction = (index) => {
            if (! nextActionSlides.length) return;
            activeNextAction = (index + nextActionSlides.length) % nextActionSlides.length;
            nextActionSlides.forEach((slide, slideIndex) => {
                slide.hidden = slideIndex !== activeNextAction;
            });
        };

        nextActionPrevious?.addEventListener('click', () => showNextAction(activeNextAction - 1));
        nextActionNext?.addEventListener('click', () => showNextAction(activeNextAction + 1));
        const preferredNextAction = @json(session('optimization_error') && $scheduleOptimization ? 'optimization' : null);
        const preferredNextActionIndex = preferredNextAction
            ? nextActionSlides.findIndex((slide) => slide.dataset.actionType === preferredNextAction)
            : -1;
        showNextAction(preferredNextActionIndex >= 0 ? preferredNextActionIndex : 0);

        const revenueSlides = Array.from(document.querySelectorAll('[data-revenue-slide]'));
        const revenuePrevious = document.querySelector('[data-revenue-prev]');
        const revenueNext = document.querySelector('[data-revenue-next]');
        let activeRevenueSlide = 0;

        const showRevenueSlide = (index) => {
            if (! revenueSlides.length) return;
            activeRevenueSlide = (index + revenueSlides.length) % revenueSlides.length;
            revenueSlides.forEach((slide, slideIndex) => {
                slide.hidden = slideIndex !== activeRevenueSlide;
            });
        };

        revenuePrevious?.addEventListener('click', () => showRevenueSlide(activeRevenueSlide - 1));
        revenueNext?.addEventListener('click', () => showRevenueSlide(activeRevenueSlide + 1));
        showRevenueSlide(0);

        const noraListeningButton = document.querySelector('[data-nora-listening]');
        const noraListeningLabel = document.querySelector('[data-nora-listening-label]');
        const dailyBriefingMessage = @json($dailyBriefing->message);
        const noraVoiceResponses = @json($noraVoiceResponses);
        const noraCallActive = @json((bool) $liveCall);
        const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
        const noraPreferenceKey = 'secretary365-nora-listening';
        let dailyBriefingMarked = @json((bool) $dailyBriefing->played_at);
        let noraModeEnabled = window.localStorage.getItem(noraPreferenceKey) === '1';
        let noraRecognition = null;
        let noraRecognitionRunning = false;
        let noraSpeaking = false;
        let noraRestartTimer = null;
        let noraStatusTimer = null;
        let noraAwaitingCommandUntil = 0;

        const preferredSpanishVoice = () => {
            const voices = window.speechSynthesis?.getVoices?.() || [];
            const spanishVoices = voices.filter((voice) => /^es([-_]|$)/i.test(voice.lang || ''));
            const preferredNames = ['helena', 'mónica', 'monica', 'paulina', 'sabina', 'luciana', 'elvira', 'google español'];

            return spanishVoices.find((voice) => preferredNames.some((name) => voice.name.toLowerCase().includes(name)))
                || spanishVoices[0]
                || null;
        };

        const markDailyBriefingPlayed = async () => {
            if (dailyBriefingMarked) return;
            dailyBriefingMarked = true;

            try {
                await fetch(@json(route('console.daily-briefing.played')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                    },
                });
            } catch (error) {
                dailyBriefingMarked = false;
            }
        };

        const updateNoraListeningStatus = (state = null) => {
            if (! noraListeningButton || ! noraListeningLabel) return;
            noraListeningButton.classList.remove('is-listening', 'is-paused', 'is-speaking');
            noraListeningButton.setAttribute('aria-pressed', noraModeEnabled ? 'true' : 'false');

            if (! SpeechRecognitionApi) {
                noraListeningLabel.textContent = 'Nora no compatible';
                noraListeningButton.disabled = true;
            } else if (! noraModeEnabled) {
                noraListeningLabel.textContent = state === 'permission' ? 'Permitir micrófono' : 'Activar Nora';
            } else if (noraCallActive) {
                noraListeningButton.classList.add('is-paused');
                noraListeningLabel.textContent = 'Nora pausada';
            } else if (noraSpeaking) {
                noraListeningButton.classList.add('is-speaking');
                noraListeningLabel.textContent = 'Nora respondiendo';
            } else {
                noraListeningButton.classList.add('is-listening');
                noraListeningLabel.textContent = state === 'starting' ? 'Activando micrófono' : 'Nora escuchando';
            }
        };

        const stopNoraRecognition = () => {
            window.clearTimeout(noraRestartTimer);
            if (! noraRecognition || ! noraRecognitionRunning) return;
            try { noraRecognition.abort(); } catch (error) {
                // El navegador ya había cerrado la escucha.
            }
        };

        const startNoraRecognition = () => {
            if (! noraModeEnabled || noraCallActive || noraSpeaking || ! noraRecognition || noraRecognitionRunning) return;
            updateNoraListeningStatus('starting');
            try { noraRecognition.start(); } catch (error) {
                noraRestartTimer = window.setTimeout(startNoraRecognition, 900);
            }
        };

        const speakWithNora = (message, { dailyBriefing = false } = {}) => {
            if (! message || ! ('speechSynthesis' in window)) return;

            noraSpeaking = true;
            stopNoraRecognition();
            updateNoraListeningStatus();
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(message);
            utterance.lang = 'es-ES';
            utterance.rate = 0.97;
            utterance.pitch = 1.03;
            utterance.volume = 1;
            const voice = preferredSpanishVoice();
            if (voice) utterance.voice = voice;

            utterance.onstart = () => {
                if (dailyBriefing) markDailyBriefingPlayed();
            };
            utterance.onend = () => {
                noraSpeaking = false;
                updateNoraListeningStatus();
                noraRestartTimer = window.setTimeout(startNoraRecognition, 500);
            };
            utterance.onerror = () => {
                noraSpeaking = false;
                updateNoraListeningStatus();
                noraRestartTimer = window.setTimeout(startNoraRecognition, 500);
            };

            window.speechSynthesis.speak(utterance);
        };

        const speakDailyBriefing = () => speakWithNora(dailyBriefingMessage, { dailyBriefing: true });
        const normalizedVoiceText = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

        const answerNoraCommand = (rawTranscript) => {
            const transcript = normalizedVoiceText(rawTranscript);
            const wakeWord = transcript.match(/\b(nora|nohra|lora|hora)\b/);
            const hasKnownIntent = ['resumen', 'proxima', 'siguiente', 'cita', 'ganancia', 'ingreso', 'facturacion', 'hueco', 'adelantar', 'agenda', 'llamada', 'recordatorio', 'ayuda']
                .some((word) => transcript.includes(word));
            if (! wakeWord && ! hasKnownIntent && Date.now() >= noraAwaitingCommandUntil) return false;
            const command = wakeWord ? transcript.slice((wakeWord.index || 0) + wakeWord[0].length).trim() : transcript;

            if (! command) {
                noraAwaitingCommandUntil = Date.now() + 10000;
                speakWithNora('Te escucho. ¿Qué quieres saber?');
                return true;
            }

            noraAwaitingCommandUntil = 0;
            if (command.includes('resumen')) speakDailyBriefing();
            else if (command.includes('proxima') || command.includes('siguiente')) speakWithNora(noraVoiceResponses.next);
            else if (command.includes('cuantas') || command.includes('numero de citas') || command === 'citas') speakWithNora(noraVoiceResponses.appointments);
            else if (command.includes('ganancia') || command.includes('ingreso') || command.includes('facturacion')) speakWithNora(noraVoiceResponses.revenue);
            else if (command.includes('hueco') || command.includes('adelantar') || command.includes('completar la agenda')) speakWithNora(noraVoiceResponses.optimization);
            else if (command.includes('llamada') || command.includes('recordatorio')) speakWithNora(noraVoiceResponses.calls);
            else if (command.includes('ayuda') || command.includes('que puedes')) speakWithNora(noraVoiceResponses.help);
            else speakWithNora('No he entendido esa petición. '.concat(noraVoiceResponses.help));
            return true;
        };

        if (SpeechRecognitionApi) {
            noraRecognition = new SpeechRecognitionApi();
            noraRecognition.lang = 'es-ES';
            noraRecognition.continuous = true;
            noraRecognition.interimResults = false;
            noraRecognition.maxAlternatives = 1;
            noraRecognition.onstart = () => { noraRecognitionRunning = true; updateNoraListeningStatus(); };
            noraRecognition.onresult = (event) => {
                for (let index = event.resultIndex; index < event.results.length; index += 1) {
                    if (! event.results[index].isFinal) continue;
                    const heard = (event.results[index][0].transcript || '').trim();
                    if (! heard) continue;

                    noraListeningButton.title = 'Último texto reconocido: “'.concat(heard, '”');
                    const answered = answerNoraCommand(heard);
                    if (! answered && noraListeningLabel) {
                        window.clearTimeout(noraStatusTimer);
                        noraListeningLabel.textContent = 'Oí: '.concat(heard.length > 24 ? heard.slice(0, 24).concat('…') : heard);
                        noraStatusTimer = window.setTimeout(updateNoraListeningStatus, 3500);
                    }
                }
            };
            noraRecognition.onerror = (event) => {
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    noraModeEnabled = false;
                    window.localStorage.removeItem(noraPreferenceKey);
                    updateNoraListeningStatus('permission');
                } else if (noraListeningLabel && event.error === 'audio-capture') {
                    noraListeningLabel.textContent = 'Micrófono no disponible';
                } else if (noraListeningLabel && event.error === 'network') {
                    noraListeningLabel.textContent = 'Servicio de voz sin conexión';
                }
            };
            noraRecognition.onend = () => {
                noraRecognitionRunning = false;
                if (noraModeEnabled && ! noraCallActive && ! noraSpeaking) noraRestartTimer = window.setTimeout(startNoraRecognition, 800);
            };
        }

        noraListeningButton?.addEventListener('click', () => {
            noraModeEnabled = ! noraModeEnabled;
            if (noraModeEnabled) {
                window.localStorage.setItem(noraPreferenceKey, '1');
                updateNoraListeningStatus('starting');
                startNoraRecognition();
            } else {
                window.localStorage.removeItem(noraPreferenceKey);
                stopNoraRecognition();
                updateNoraListeningStatus();
            }
        });

        updateNoraListeningStatus();
        if (noraModeEnabled) window.setTimeout(startNoraRecognition, 600);
        window.addEventListener('beforeunload', stopNoraRecognition);

        let displayedCallStatus = @json($liveCall->status ?? null);
        let refreshingCallPanel = false;
        const refreshCallPanel = async () => {
            if (refreshingCallPanel || document.visibilityState !== 'visible') return;

            try {
                const response = await fetch(@json(url('/consola/llamada-activa')), {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (! response.ok) return;

                const call = await response.json();
                const currentStatus = call.active ? call.status : null;
                if (currentStatus !== displayedCallStatus) {
                    refreshingCallPanel = true;
                    window.location.reload();
                }
            } catch (error) {
                // La siguiente comprobación volverá a intentarlo sin interrumpir la consola.
            }
        };
        window.setInterval(refreshCallPanel, 5000);

    </script>
@endsection
