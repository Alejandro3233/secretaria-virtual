@extends('layouts.app')

@section('title', 'Agenda - Secretaria Virtual')
@section('page_title', 'Agenda')
@section('page_subtitle', $clinic?->google_connected_at ? 'Agenda sincronizada con Google Calendar y guardada en Secretaria Virtual.' : 'Conecta Google Calendar para importar y sincronizar citas automaticamente.')
@section('page_actions')
    <a class="btn" href="/citas">Lista de citas</a>
    @if ($clinic?->google_connected_at)
        <form method="POST" action="/google-calendar/sync">
            @csrf
            <button class="btn" type="submit">Sincronizar Google</button>
        </form>
    @else
        <a class="btn" href="/google-calendar/connect">Conectar Google Calendar</a>
    @endif
@endsection

@section('content')
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

    @if (! $clinic?->google_connected_at)
        <section class="card" style="margin-bottom:18px;">
            <div class="section-title">
                <div>
                    <h2>Conecta Google Calendar para activar la agenda real</h2>
                    <span class="subtitle">Cuando el salon o su sistema externo cree citas en Google Calendar, Secretaria Virtual las importara a la base local para poder atender llamadas, confirmar, cambiar o cancelar.</span>
                </div>
                <a class="btn primary" href="/google-calendar/connect">Conectar ahora</a>
            </div>
        </section>
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
                    @endphp
                    <label class="stylist-filter">
                        <input type="checkbox" name="stylists[]" value="{{ $stylist->id }}" onchange="this.form.submit()" @checked($isChecked)>
                        <span class="stylist-check {{ $stylistColorClass }}"></span>
                        <span>{{ $stylist->name }}</span>
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

                @if ($appointments->isEmpty())
                    <div class="calendar-empty">
                        <div>
                            <b>No hay citas en esta vista</b>
                            <span>Sincroniza Google Calendar, cambia el dia o ajusta los estilistas seleccionados.</span>
                        </div>
                        @if ($clinic?->google_connected_at)
                            <form method="POST" action="/google-calendar/sync">
                                @csrf
                                <button class="btn primary" type="submit">Sincronizar ahora</button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($selectedView === 'day')
                    <div class="calendar-week calendar-day-view" style="grid-template-columns: 74px repeat({{ max(1, $visibleStylists->count()) }}, minmax(160px, 1fr));">
                        <div class="calendar-timezone">GMT{{ now()->format('P') }}</div>
                        @forelse ($visibleStylists as $stylist)
                            <div class="calendar-day-head">
                                <span>Estilista</span>
                                <b title="{{ $stylist->name }}">{{ substr($stylist->name, 0, 1) }}</b>
                                <small>{{ $stylist->name }}</small>
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
                            @endphp
                            <div class="calendar-day-column" style="height: {{ $calendarHeight }}px;">
                                @for ($hour = $calendarStartHour; $hour < $calendarEndHour; $hour++)
                                    <div class="calendar-hour-line" style="top: {{ ($hour - $calendarStartHour) * $hourHeight }}px;"></div>
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
                                    <span>{{ $appointment->starts_at->format('g:i') }} {{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</span>
                                @endforeach
                                @if ($dayAppointments->count() > 3)
                                    <small>+{{ $dayAppointments->count() - 3 }} mas</small>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="calendar-week">
                        <div class="calendar-timezone">GMT{{ now()->format('P') }}</div>
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
                            <div class="calendar-day-column" style="height: {{ $calendarHeight }}px;">
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
            </section>
        </div>
    </div>
@endsection
