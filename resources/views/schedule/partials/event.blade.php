@php
    $startsAt = $appointment->starts_at;
    $endsAt = $appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes(45);
    $durationMinutes = max(30, $startsAt->diffInMinutes($endsAt));
    $top = (($startsAt->hour - $calendarStartHour) * $hourHeight) + (($startsAt->minute / 60) * $hourHeight);
    $height = max(34, ($durationMinutes / 60) * $hourHeight);
    $sourceClass = $appointment->source === 'google_calendar' ? 'from-google' : ($appointment->source === 'twilio' ? 'from-twilio' : 'from-web');
    $clientName = trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? ''));
@endphp

<a
    class="calendar-event {{ $sourceClass }}"
    href="/citas/{{ $appointment->id }}/editar"
    draggable="true"
    data-appointment-id="{{ $appointment->id }}"
    data-client-name="{{ $clientName }}"
    data-duration-minutes="{{ $durationMinutes }}"
    data-move-url="/agenda/citas/{{ $appointment->id }}/mover"
    style="top: {{ $top }}px; height: {{ $height }}px;"
>
    <b>{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</b>
    <span>{{ $startsAt->format('g:i A') }} - {{ $endsAt->format('g:i A') }}</span>
    <small>{{ $clientName }} - {{ $appointment->stylist?->name ?? $appointment->chair_station ?? 'Sin asignar' }}</small>
</a>
