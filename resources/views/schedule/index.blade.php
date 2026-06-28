@extends('layouts.app')

@section('title', 'Agenda - Secretary365')
@section('page_title', 'Agenda')
@section('page_subtitle', 'Consulta y organiza las citas del salon.')
@section('page_actions')
    <a class="btn" href="/citas">Lista de citas</a>
    <span class="calendar-connection-status {{ $clinic?->google_connected_at ? 'is-connected' : 'is-disconnected' }}" role="status" tabindex="0" aria-label="Google Calendar {{ $clinic?->google_connected_at ? 'conectado' : 'sin conectar' }}">
        <span class="calendar-connection-dot" aria-hidden="true"></span>
        Google Calendar
        <span class="calendar-connection-help" role="tooltip">
            @if ($clinic?->google_connected_at)
                Tu agenda ya esta conectada. Para revisar o cambiar la conexion, abre <b>Ajustes</b> y entra en la seccion <b>Google Calendar</b>.
            @else
                &iquest;Quieres conectar tu agenda? Abre <b>Ajustes</b> en el menu lateral, busca la seccion <b>Google Calendar</b> y sigue los pasos que aparecen en pantalla.
            @endif
        </span>
    </span>
@endsection

@section('content')
    <style>
        .calendar-connection-status { position: relative; min-height: 34px; display: inline-flex; align-items: center; gap: 7px; padding: 0 10px; border: 1px solid var(--line); border-radius: 999px; background: #fff; color: var(--muted); font-size: 12px; font-weight: 800; white-space: nowrap; cursor: help; }
        .calendar-connection-status:focus-visible { outline: 3px solid rgba(29,78,216,.22); outline-offset: 2px; }
        .calendar-connection-dot { width: 8px; height: 8px; flex: 0 0 8px; border-radius: 50%; background: #dc2626; box-shadow: 0 0 0 3px #fee2e2; }
        .calendar-connection-status.is-connected .calendar-connection-dot { background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; }
        .calendar-connection-help { position: absolute; z-index: 30; top: calc(100% + 10px); right: 0; width: min(310px, calc(100vw - 32px)); padding: 12px 14px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--ink); box-shadow: 0 12px 32px rgba(36,21,29,.14); font-size: 13px; font-weight: 400; line-height: 1.45; white-space: normal; opacity: 0; visibility: hidden; transform: translateY(-4px); pointer-events: none; transition: opacity .15s ease, transform .15s ease, visibility .15s ease; }
        .calendar-connection-help::before { content: ""; position: absolute; right: 18px; bottom: 100%; border: 6px solid transparent; border-bottom-color: #fff; filter: drop-shadow(0 -1px 0 var(--line)); }
        .calendar-connection-status:hover .calendar-connection-help,
        .calendar-connection-status:focus .calendar-connection-help { opacity: 1; visibility: visible; transform: translateY(0); }
        .move-confirmation[hidden] { display: none; }
        .move-confirmation { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 20px; background: rgba(24,18,22,.62); }
        .move-confirmation-card { width: min(430px, 100%); border-radius: 10px; padding: 28px; background: white; box-shadow: 0 24px 70px rgba(0,0,0,.28); text-align: center; }
        .move-confirmation-card h2 { font-size: 24px; line-height: 1.25; }
        .move-confirmation-card p { margin: 12px 0 22px; color: var(--muted); line-height: 1.55; }
        .move-confirmation-actions { display: grid; gap: 10px; }
        .move-confirmation-actions .btn { min-height: 44px; }
        .calendar-nonworking-hours { position: absolute; left: 0; right: 0; z-index: 0; background: #e5e7eb; pointer-events: none; }
        .calendar-vacation-hours { position:absolute;inset:0;z-index:1;display:flex;align-items:flex-start;justify-content:center;padding-top:18px;background:rgba(209,213,219,.88);color:#4b5563;font-size:12px;font-weight:900;letter-spacing:.02em;pointer-events:none; }
        .calendar-day-column.is-vacation { cursor:not-allowed; }
        .calendar-break-hours { position:absolute;left:0;right:0;z-index:1;display:flex;align-items:center;justify-content:center;border-top:1px dashed #9ca3af;border-bottom:1px dashed #9ca3af;background:repeating-linear-gradient(135deg,#e5e7eb,#e5e7eb 8px,#f3f4f6 8px,#f3f4f6 16px);color:#4b5563;font-size:11px;font-weight:900;pointer-events:none; }
        .calendar-stylist-avatar { width:34px;height:34px;display:grid;place-items:center;overflow:hidden;margin:auto;border-radius:50%;background:#f3e8ee;color:var(--brand);font-size:11px;font-weight:900;box-shadow:0 0 0 2px #fff,0 0 0 3px var(--line); }
        .calendar-stylist-avatar img { width:100%;height:100%;object-fit:cover; }
        .calendar-day-head .calendar-stylist-name { margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ink);font-size:12px;font-weight:900; }
    </style>
    @php
        $calendarStartHour = collect($hours)->min() ?? 9;
        $calendarEndHour = (collect($hours)->max() ?? 17) + 1;
        $hourHeight = 64;
        $calendarHeight = max(1, $calendarEndHour - $calendarStartHour) * $hourHeight;
        $selectedStylistQuery = $selectedStylistIds->all();
        $viewQuery = fn (string $view) => http_build_query([
            'date' => $selectedDate->format('Y-m-d'),
            'view' => $view,
            'stylists' => $selectedStylistQuery,
        ]);
        $dateQuery = fn ($date) => http_build_query([
            'date' => $date->format('Y-m-d'),
            'view' => $selectedView,
            'stylists' => $selectedStylistQuery,
        ]);
    @endphp

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

    @if ($googleCalendarError)
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            No se pudo sincronizar Google Calendar automaticamente: {{ $googleCalendarError }}
        </div>
    @endif

    <div class="google-calendar-layout">
        <aside class="google-calendar-sidebar" aria-label="Controles de agenda">
            <a class="calendar-create-btn" href="/agenda/nueva-cita">
                <span>+</span>
                Crear
            </a>

            <section class="mini-calendar" aria-label="Mini calendario">
                <div class="mini-calendar-head">
                    <b>{{ ucfirst($selectedDate->locale('es')->isoFormat('MMMM [de] YYYY')) }}</b>
                    <div>
                        <a href="/agenda?{{ $dateQuery($selectedDate->copy()->subMonth()) }}" aria-label="Mes anterior">&lt;</a>
                        <a href="/agenda?{{ $dateQuery($selectedDate->copy()->addMonth()) }}" aria-label="Mes siguiente">&gt;</a>
                    </div>
                </div>
                <div class="mini-calendar-grid">
                    @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $weekday)
                        <span class="mini-weekday">{{ $weekday }}</span>
                    @endforeach
                    @foreach ($miniCalendarDays as $miniDay)
                        @php
                            $miniDayClasses = collect([
                                ! $miniDay->isSameMonth($selectedDate) ? 'is-muted' : null,
                                $miniDay->isToday() ? 'is-today' : null,
                                $selectedView === 'day' && $miniDay->isSameDay($selectedDate) ? 'is-selected-week' : null,
                                $selectedView !== 'day' && $miniDay->betweenIncluded($weekStart, $weekEnd) ? 'is-selected-week' : null,
                            ])->filter()->implode(' ');
                        @endphp
                        <a class="{{ $miniDayClasses }}" href="/agenda?{{ $dateQuery($miniDay) }}">
                            {{ $miniDay->format('j') }}
                        </a>
                    @endforeach
                </div>
            </section>

            <form class="stylist-calendar-list" method="GET" action="/agenda">
                <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                <input type="hidden" name="view" value="{{ $selectedView }}">
                <div class="sidebar-section-title">
                    <b>Agendas por estilista</b>
                    <span>{{ $stylists->count() }}</span>
                </div>
                @forelse ($stylists as $stylist)
                    @php
                        $stylistColorClass = 'stylist-color-'.(($loop->index % 6) + 1);
                        $isChecked = $selectedStylistIds->isEmpty() || $selectedStylistIds->contains($stylist->id);
                        $stylistDisplayName = $stylist->is_internal && ! $clinic->google_connected_at ? 'Google (desconectado)' : $stylist->name;
                    @endphp
                    <label class="stylist-filter">
                        <input type="checkbox" name="stylists[]" value="{{ $stylist->id }}" onchange="this.form.submit()" @checked($isChecked)>
                        <span class="stylist-check {{ $stylistColorClass }}"></span>
                        <span>{{ $stylistDisplayName }}</span>
                    </label>
                @empty
                    <div class="calendar-sidebar-empty">No hay estilistas activos.</div>
                @endforelse
            </form>
        </aside>

        <div class="google-calendar-main">
            <section class="calendar-shell" aria-label="Agenda">
                <div class="calendar-toolbar">
                    <div>
                        <div class="calendar-kicker">{{ $selectedView === 'day' ? 'Dia' : ($selectedView === 'month' ? 'Mes' : 'Semana') }}</div>
                        <h2>
                            @if ($selectedView === 'day')
                                {{ ucfirst($selectedDate->locale('es')->isoFormat('dddd, D [de] MMMM YYYY')) }}
                            @elseif ($selectedView === 'month')
                                {{ ucfirst($selectedDate->locale('es')->isoFormat('MMMM [de] YYYY')) }}
                            @else
                                {{ $weekStart->locale('es')->isoFormat('D MMM') }} - {{ $weekEnd->locale('es')->isoFormat('D MMM YYYY') }}
                            @endif
                        </h2>
                    </div>
                    <div class="calendar-view-tabs" aria-label="Vista de calendario">
                        <a class="{{ $selectedView === 'day' ? 'active' : '' }}" href="/agenda?{{ $viewQuery('day') }}">Dia</a>
                        <a class="{{ $selectedView === 'week' ? 'active' : '' }}" href="/agenda?{{ $viewQuery('week') }}">Semana</a>
                        <a class="{{ $selectedView === 'month' ? 'active' : '' }}" href="/agenda?{{ $viewQuery('month') }}">Mes</a>
                    </div>
                </div>

                @if ($selectedView === 'day')
                    <div class="calendar-week calendar-day-view" style="grid-template-columns: 74px repeat({{ max(1, $visibleStylists->count()) }}, minmax(160px, 1fr));">
                        <div class="calendar-timezone">GMT{{ now($timezone)->format('P') }}</div>
                        @forelse ($visibleStylists as $stylist)
                            @php
                                $stylistDisplayName = $stylist->is_internal && ! $clinic->google_connected_at ? 'Google (desconectado)' : $stylist->name;
                            @endphp
                            <div class="calendar-day-head">
                                <span class="calendar-stylist-avatar" title="{{ $stylistDisplayName }}">@if($stylist->avatarUrl())<img src="{{ $stylist->avatarUrl() }}" alt="Foto de {{ $stylistDisplayName }}">@else{{ $stylist->initials() }}@endif</span>
                                <small class="calendar-stylist-name">{{ $stylistDisplayName }}</small>
                            </div>
                        @empty
                            <div class="calendar-day-head">
                                <span>Sin personal</span>
                                <b>-</b>
                            </div>
                        @endforelse

                        <div class="calendar-time-gutter" style="height: {{ $calendarHeight }}px;">
                            @for ($hour = $calendarStartHour; $hour < $calendarEndHour; $hour++)
                                <div class="calendar-hour-label" style="top: {{ ($hour - $calendarStartHour) * $hourHeight }}px;">
                                    {{ $hour === 0 ? '12 AM' : ($hour > 12 ? $hour - 12 : $hour).' '.($hour >= 12 ? 'PM' : 'AM') }}
                                </div>
                            @endfor
                        </div>

                        @forelse ($visibleStylists as $stylist)
                            @php
                                $stylistAppointments = $appointments->filter(fn ($appointment) => $appointment->stylist_id === $stylist->id);
                                $stylistVacation = $stylist->vacations->first(fn ($vacation) =>
                                    $vacation->starts_on->startOfDay()->lte($selectedDate->copy()->endOfDay())
                                    && $vacation->ends_on->endOfDay()->gte($selectedDate->copy()->startOfDay())
                                );
                                $stylistDaySchedule = $stylist->scheduleForDate($selectedDate);
                                $stylistWorksToday = $stylistDaySchedule !== null;
                                [$workStartHour, $workStartMinute] = array_pad(array_map('intval', explode(':', $stylistDaySchedule['start'] ?? '08:00')), 2, 0);
                                [$workEndHour, $workEndMinute] = array_pad(array_map('intval', explode(':', $stylistDaySchedule['end'] ?? '21:00')), 2, 0);
                                $calendarStartMinutes = $calendarStartHour * 60;
                                $calendarEndMinutes = $calendarEndHour * 60;
                                $workStartMinutes = max($calendarStartMinutes, min($calendarEndMinutes, ($workStartHour * 60) + $workStartMinute));
                                $workEndMinutes = max($calendarStartMinutes, min($calendarEndMinutes, ($workEndHour * 60) + $workEndMinute));
                                $beforeWorkHeight = (($workStartMinutes - $calendarStartMinutes) / 60) * $hourHeight;
                                $afterWorkTop = (($workEndMinutes - $calendarStartMinutes) / 60) * $hourHeight;
                                $breakStartMinutes = $breakEndMinutes = null;
                                if ($stylistWorksToday && !empty($stylistDaySchedule['break_start']) && !empty($stylistDaySchedule['break_end'])) {
                                    [$breakStartHour, $breakStartMinute] = array_pad(array_map('intval', explode(':', $stylistDaySchedule['break_start'])), 2, 0);
                                    [$breakEndHour, $breakEndMinute] = array_pad(array_map('intval', explode(':', $stylistDaySchedule['break_end'])), 2, 0);
                                    $breakStartMinutes = max($calendarStartMinutes, min($calendarEndMinutes, ($breakStartHour * 60) + $breakStartMinute));
                                    $breakEndMinutes = max($calendarStartMinutes, min($calendarEndMinutes, ($breakEndHour * 60) + $breakEndMinute));
                                }
                            @endphp
                            <div
                                class="calendar-day-column {{ $stylistVacation ? 'is-vacation' : '' }}"
                                data-drop-column="day"
                                @if ($stylistVacation) data-unavailable="vacation" @endif
                                data-date="{{ $selectedDate->format('Y-m-d') }}"
                                data-stylist-id="{{ $stylist->id }}"
                                data-start-hour="{{ $calendarStartHour }}"
                                data-hour-height="{{ $hourHeight }}"
                                style="height: {{ $calendarHeight }}px;"
                            >
                                @if ($stylistVacation)
                                    <div class="calendar-vacation-hours" title="{{ $stylistVacation->reason ?: 'Vacaciones' }}">Vacaciones</div>
                                @elseif (! $stylistWorksToday)
                                    <div class="calendar-nonworking-hours" style="top:0;height:{{ $calendarHeight }}px;"></div>
                                @else
                                    @if ($beforeWorkHeight > 0)
                                        <div class="calendar-nonworking-hours" style="top:0;height:{{ $beforeWorkHeight }}px;"></div>
                                    @endif
                                    @if ($afterWorkTop < $calendarHeight)
                                        <div class="calendar-nonworking-hours" style="top:{{ $afterWorkTop }}px;height:{{ $calendarHeight - $afterWorkTop }}px;"></div>
                                    @endif
                                    @if ($breakStartMinutes !== null && $breakEndMinutes > $breakStartMinutes)
                                        <div class="calendar-break-hours" style="top:{{ (($breakStartMinutes - $calendarStartMinutes) / 60) * $hourHeight }}px;height:{{ (($breakEndMinutes - $breakStartMinutes) / 60) * $hourHeight }}px;">
                                            Descanso {{ \Illuminate\Support\Carbon::createFromTime(intdiv($breakStartMinutes,60),$breakStartMinutes%60)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::createFromTime(intdiv($breakEndMinutes,60),$breakEndMinutes%60)->format('g:i A') }}
                                        </div>
                                    @endif
                                @endif
                                @for ($hour = $calendarStartHour; $hour < $calendarEndHour; $hour++)
                                    <div class="calendar-hour-line" style="top: {{ ($hour - $calendarStartHour) * $hourHeight }}px;"></div>
                                    @foreach ([15, 30, 45] as $minuteMark)
                                        <div
                                            class="calendar-quarter-line {{ $minuteMark === 30 ? 'is-half' : '' }}"
                                            style="top: {{ (($hour - $calendarStartHour) * $hourHeight) + (($minuteMark / 60) * $hourHeight) }}px;"
                                        ></div>
                                    @endforeach
                                @endfor
                                @foreach ($stylistAppointments as $appointment)
                                    @include('schedule.partials.event', ['appointment' => $appointment, 'calendarStartHour' => $calendarStartHour, 'hourHeight' => $hourHeight])
                                @endforeach
                            </div>
                        @empty
                            <div class="calendar-day-column" style="height: {{ $calendarHeight }}px;"></div>
                        @endforelse
                    </div>
                @elseif ($selectedView === 'month')
                    <div class="calendar-month">
                        @foreach (['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $weekday)
                            <div class="calendar-month-weekday">{{ $weekday }}</div>
                        @endforeach
                        @foreach ($monthDays as $monthDay)
                            @php
                                $dayAppointments = $appointments->filter(fn ($appointment) => $appointment->starts_at->isSameDay($monthDay));
                            @endphp
                            <a class="calendar-month-day {{ ! $monthDay->isSameMonth($selectedDate) ? 'is-muted' : '' }} {{ $monthDay->isToday() ? 'is-today' : '' }}" href="/agenda?{{ http_build_query(['date' => $monthDay->format('Y-m-d'), 'view' => 'day', 'stylists' => $selectedStylistQuery]) }}">
                                <b>{{ $monthDay->format('j') }}</b>
                                @foreach ($dayAppointments->take(3) as $appointment)
                                    @php
                                        $monthAppointmentEnd = $appointment->ends_at
                                            ?? $appointment->starts_at->copy()->addMinutes($appointment->service?->duration_minutes ?? 60);
                                    @endphp
                                    <span class="calendar-month-event {{ $appointment->trafficLightClass() }} {{ $monthAppointmentEnd->isPast() ? 'is-past' : '' }}" title="{{ $appointment->trafficLightLabel() }}">
                                        @if ($appointment->source === 'google_calendar')
                                            <img class="appointment-google-badge" src="/google-g.svg" alt="" title="Importada desde Google Calendar">
                                        @endif
                                        {{ $appointment->starts_at->format('g:i') }} {{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}
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
                                @endforeach
                                @if ($dayAppointments->count() > 3)
                                    <small>+{{ $dayAppointments->count() - 3 }} mas</small>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="calendar-week">
                        <div class="calendar-timezone">GMT{{ now($timezone)->format('P') }}</div>
                        @foreach ($days as $day)
                            <div class="calendar-day-head {{ $day->isToday() ? 'is-today' : '' }}">
                                <span>{{ ucfirst($day->locale('es')->isoFormat('ddd')) }}</span>
                                <b>{{ $day->format('j') }}</b>
                            </div>
                        @endforeach

                        <div class="calendar-time-gutter" style="height: {{ $calendarHeight }}px;">
                            @for ($hour = $calendarStartHour; $hour < $calendarEndHour; $hour++)
                                <div class="calendar-hour-label" style="top: {{ ($hour - $calendarStartHour) * $hourHeight }}px;">
                                    {{ $hour === 0 ? '12 AM' : ($hour > 12 ? $hour - 12 : $hour).' '.($hour >= 12 ? 'PM' : 'AM') }}
                                </div>
                            @endfor
                        </div>

                        @foreach ($days as $day)
                            @php
                                $dayAppointments = $appointments->filter(fn ($appointment) => $appointment->starts_at->isSameDay($day));
                            @endphp
                            <div
                                class="calendar-day-column"
                                data-drop-column="week"
                                data-date="{{ $day->format('Y-m-d') }}"
                                data-start-hour="{{ $calendarStartHour }}"
                                data-hour-height="{{ $hourHeight }}"
                                style="height: {{ $calendarHeight }}px;"
                            >
                                @for ($hour = $calendarStartHour; $hour < $calendarEndHour; $hour++)
                                    <div class="calendar-hour-line" style="top: {{ ($hour - $calendarStartHour) * $hourHeight }}px;"></div>
                                @endfor

                                @foreach ($dayAppointments as $appointment)
                                    @include('schedule.partials.event', ['appointment' => $appointment, 'calendarStartHour' => $calendarStartHour, 'hourHeight' => $hourHeight])
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (in_array($selectedView, ['day', 'week'], true))
                    <div class="calendar-drag-status" role="status" aria-live="polite" hidden></div>
                    <div class="move-confirmation" role="dialog" aria-modal="true" aria-labelledby="move-confirmation-title" hidden>
                        <div class="move-confirmation-card">
                            <h2 id="move-confirmation-title">¿Deseas mover esta cita?</h2>
                            <p data-move-confirmation-details></p>
                            <div class="move-confirmation-actions">
                                <button class="btn primary" type="button" data-move-confirm>Guardar cambios</button>
                                <button class="btn" type="button" data-move-cancel>Volver</button>
                            </div>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @if (in_array($selectedView, ['day', 'week'], true))
        <script>
            (() => {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const columns = [...document.querySelectorAll('[data-drop-column]')];
                const status = document.querySelector('.calendar-drag-status');
                const confirmation = document.querySelector('.move-confirmation');
                const confirmationDetails = confirmation?.querySelector('[data-move-confirmation-details]');
                const confirmationAccept = confirmation?.querySelector('[data-move-confirm]');
                const confirmationCancel = confirmation?.querySelector('[data-move-cancel]');
                const preview = document.createElement('div');
                preview.className = 'calendar-drop-preview';
                preview.hidden = true;
                const trafficClasses = [
                    'appointment-confirmed', 'appointment-cancelled', 'appointment-pending',
                    'appointment-urgent-light', 'appointment-urgent-medium', 'appointment-urgent-high'
                ];
                let draggedEvent = null;
                let clickStart = null;

                const refreshPendingTraffic = (event) => {
                    if (event.dataset.endAt) {
                        event.classList.toggle('is-past', new Date(event.dataset.endAt).getTime() <= Date.now());
                    }
                    if (event.dataset.appointmentStatus !== 'pending' || ! event.dataset.startAt) return;

                    const hoursUntilStart = (new Date(event.dataset.startAt).getTime() - Date.now()) / 3600000;
                    const traffic = hoursUntilStart > 24
                        ? ['appointment-pending', 'Pendiente, faltan más de 24 horas']
                        : hoursUntilStart > 12
                            ? ['appointment-urgent-light', 'Pendiente, faltan menos de 24 horas']
                            : hoursUntilStart > 6
                                ? ['appointment-urgent-medium', 'Pendiente, faltan menos de 12 horas']
                                : ['appointment-urgent-high', 'Pendiente, faltan menos de 6 horas'];

                    event.classList.remove(...trafficClasses);
                    event.classList.add(traffic[0]);
                    const hoverDetails = `${traffic[1]}\nCliente: ${event.dataset.clientName || 'Cliente'}\nEspecialista: ${event.dataset.stylistName || 'Sin asignar'}`;
                    event.title = hoverDetails;
                    event.setAttribute('aria-label', hoverDetails);
                };

                const refreshAllPendingTraffic = () => document.querySelectorAll('.calendar-event').forEach(refreshPendingTraffic);
                refreshAllPendingTraffic();
                window.setInterval(refreshAllPendingTraffic, 60000);

                const showStatus = (message, type = 'info') => {
                    if (! status) {
                        return;
                    }

                    status.textContent = message;
                    status.dataset.type = type;
                    status.hidden = false;
                    window.clearTimeout(showStatus.timer);
                    showStatus.timer = window.setTimeout(() => {
                        status.hidden = true;
                    }, 3200);
                };

                const snapMinutes = (value) => Math.max(0, Math.min(1439, Math.round(value / 5) * 5));
                const previewTop = (minutes, startHour, hourHeight) => ((minutes - (startHour * 60)) / 60) * hourHeight;
                const formatTime = (minutes) => {
                    const hour = Math.floor(minutes / 60);
                    const minute = minutes % 60;
                    const period = hour >= 12 ? 'PM' : 'AM';
                    const hour12 = hour % 12 || 12;

                    return `${hour12}:${String(minute).padStart(2, '0')} ${period}`;
                };
                const updatePreview = (column, clientY) => {
                    if (! draggedEvent) {
                        return null;
                    }

                    const rect = column.getBoundingClientRect();
                    const startHour = Number(column.dataset.startHour);
                    const hourHeight = Number(column.dataset.hourHeight);
                    const duration = Number(draggedEvent.dataset.durationMinutes || 60);
                    const minutes = snapMinutes(startHour * 60 + ((clientY - rect.top) / hourHeight) * 60);

                    preview.style.top = `${previewTop(minutes, startHour, hourHeight)}px`;
                    preview.style.height = draggedEvent.style.height || `${Math.max(34, (duration / 60) * hourHeight)}px`;
                    preview.textContent = formatTime(minutes);

                    if (preview.parentElement !== column) {
                        column.appendChild(preview);
                    }

                    preview.hidden = false;

                    return { minutes, startHour, hourHeight };
                };
                const hidePreview = () => {
                    preview.hidden = true;
                    preview.remove();
                };
                const confirmMove = (details) => new Promise((resolve) => {
                    if (! confirmation || ! confirmationAccept || ! confirmationCancel) {
                        resolve(true);
                        return;
                    }

                    confirmationDetails.textContent = details;
                    confirmation.hidden = false;
                    const finish = (accepted) => {
                        confirmation.hidden = true;
                        confirmationAccept.onclick = null;
                        confirmationCancel.onclick = null;
                        confirmation.onclick = null;
                        document.removeEventListener('keydown', onKeydown);
                        resolve(accepted);
                    };
                    const onKeydown = (event) => {
                        if (event.key === 'Escape') finish(false);
                    };
                    confirmationAccept.onclick = () => finish(true);
                    confirmationCancel.onclick = () => finish(false);
                    confirmation.onclick = (event) => {
                        if (event.target === confirmation) finish(false);
                    };
                    document.addEventListener('keydown', onKeydown);
                    confirmationAccept.focus();
                });

                document.querySelectorAll('.calendar-event').forEach((event) => {
                    event.addEventListener('pointerdown', (pointerEvent) => {
                        clickStart = { x: pointerEvent.clientX, y: pointerEvent.clientY };
                    });

                    event.addEventListener('click', (clickEvent) => {
                        if (! clickStart) {
                            return;
                        }

                        const distance = Math.hypot(clickEvent.clientX - clickStart.x, clickEvent.clientY - clickStart.y);
                        if (distance > 6) {
                            clickEvent.preventDefault();
                        }
                    });

                    event.addEventListener('dragstart', (dragEvent) => {
                        draggedEvent = event;
                        event.classList.add('is-dragging');
                        dragEvent.dataTransfer.effectAllowed = 'move';
                        dragEvent.dataTransfer.setData('text/plain', event.dataset.appointmentId);
                    });

                    event.addEventListener('dragend', () => {
                        event.classList.remove('is-dragging');
                        columns.forEach((column) => column.classList.remove('is-drag-over'));
                        hidePreview();
                        draggedEvent = null;
                    });
                });

                columns.forEach((column) => {
                    column.addEventListener('dragover', (dragEvent) => {
                        if (! draggedEvent) {
                            return;
                        }

                        if (column.dataset.unavailable === 'vacation') {
                            dragEvent.dataTransfer.dropEffect = 'none';
                            hidePreview();
                            return;
                        }

                        dragEvent.preventDefault();
                        dragEvent.dataTransfer.dropEffect = 'move';
                        column.classList.add('is-drag-over');
                        updatePreview(column, dragEvent.clientY);
                    });

                    column.addEventListener('dragleave', () => {
                        column.classList.remove('is-drag-over');
                        if (preview.parentElement === column) {
                            hidePreview();
                        }
                    });

                    column.addEventListener('drop', async (dropEvent) => {
                        if (! draggedEvent) {
                            return;
                        }

                        if (column.dataset.unavailable === 'vacation') {
                            dropEvent.preventDefault();
                            showStatus('Este empleado esta de vacaciones ese dia.', 'danger');
                            hidePreview();
                            return;
                        }

                        dropEvent.preventDefault();
                        column.classList.remove('is-drag-over');

                        const movedEvent = draggedEvent;
                        const dropPosition = updatePreview(column, dropEvent.clientY);
                        const startHour = dropPosition.startHour;
                        const hourHeight = dropPosition.hourHeight;
                        const minutes = dropPosition.minutes;
                        const previousParent = movedEvent.parentElement;
                        const previousTop = movedEvent.style.top;
                        const previousText = movedEvent.querySelector('span')?.textContent;
                        const duration = Number(movedEvent.dataset.durationMinutes || 60);

                        movedEvent.style.top = `${previewTop(minutes, startHour, hourHeight)}px`;
                        movedEvent.querySelector('span').textContent = `${formatTime(minutes)} - ${formatTime(minutes + duration)}`;
                        column.appendChild(movedEvent);
                        hidePreview();

                        const formattedDate = new Intl.DateTimeFormat('es-ES', {
                            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
                        }).format(new Date(`${column.dataset.date}T00:00:00`));
                        const accepted = await confirmMove(
                            `${movedEvent.dataset.clientName || 'Cliente'} · ${formattedDate} · ${formatTime(minutes)} a ${formatTime(minutes + duration)}`
                        );
                        if (! accepted) {
                            previousParent.appendChild(movedEvent);
                            movedEvent.style.top = previousTop;
                            if (previousText && movedEvent.querySelector('span')) {
                                movedEvent.querySelector('span').textContent = previousText;
                            }
                            showStatus('El cambio de horario fue cancelado.', 'info');
                            return;
                        }

                        movedEvent.classList.add('is-saving');

                        try {
                            const response = await fetch(movedEvent.dataset.moveUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    _method: 'PUT',
                                    date: column.dataset.date,
                                    minutes,
                                    stylist_id: column.dataset.stylistId || movedEvent.dataset.stylistId || null,
                                }),
                            });
                            const payload = await response.json().catch(() => ({
                                message: `Laravel respondio ${response.status}. Revisa la consola del servidor.`
                            }));

                            if (! response.ok) {
                                throw new Error(payload.message || 'No se pudo mover la cita.');
                            }

                            if (payload.appointment?.starts_at && payload.appointment?.ends_at && movedEvent.querySelector('span')) {
                                movedEvent.querySelector('span').textContent = `${payload.appointment.starts_at} - ${payload.appointment.ends_at}`;
                            }

                            if (payload.appointment?.stylist && movedEvent.querySelector('small')) {
                                movedEvent.querySelector('small').textContent = `${movedEvent.dataset.clientName} - ${payload.appointment.stylist}`;
                            }

                            if (payload.appointment?.starts_at_iso) {
                                movedEvent.dataset.startAt = payload.appointment.starts_at_iso;
                            }
                            if (payload.appointment?.ends_at_iso) {
                                movedEvent.dataset.endAt = payload.appointment.ends_at_iso;
                            }
                            movedEvent.dataset.stylistName = payload.appointment?.stylist || 'Sin asignar';

                            if (payload.appointment?.traffic_class) {
                                movedEvent.classList.remove(...trafficClasses);
                                movedEvent.classList.add(payload.appointment.traffic_class);
                            }

                            if (payload.appointment?.traffic_label) {
                                const stylistName = payload.appointment.stylist || 'Sin asignar';
                                const hoverDetails = `${payload.appointment.traffic_label}\nCliente: ${movedEvent.dataset.clientName || 'Cliente'}\nEspecialista: ${stylistName}`;
                                movedEvent.title = hoverDetails;
                                movedEvent.setAttribute('aria-label', hoverDetails);
                            }

                            showStatus(payload.message || 'Cita movida correctamente.', 'ok');
                        } catch (error) {
                            previousParent.appendChild(movedEvent);
                            movedEvent.style.top = previousTop;
                            if (previousText && movedEvent.querySelector('span')) {
                                movedEvent.querySelector('span').textContent = previousText;
                            }
                            showStatus(error.message, 'danger');
                        } finally {
                            movedEvent.classList.remove('is-saving');
                        }
                    });
                });
            })();
        </script>
    @endif
@endsection
