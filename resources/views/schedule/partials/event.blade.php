@php
    $startsAt = $appointment->starts_at;
    $endsAt = $appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes(45);
    $durationMinutes = max(5, $startsAt->diffInMinutes($endsAt));
    $top = (($startsAt->hour - $calendarStartHour) * $hourHeight) + (($startsAt->minute / 60) * $hourHeight);
    $height = max(14, ($durationMinutes / 60) * $hourHeight);
    $pastClass = $endsAt->isPast() ? 'is-past' : '';
    $sourceClass = $appointment->source === 'google_calendar' ? 'from-google' : ($appointment->source === 'twilio' ? 'from-twilio' : 'from-web');
    $trafficClass = $appointment->trafficLightClass();
    $durationClass = $durationMinutes <= 15
        ? 'is-compact is-very-compact is-micro'
        : ($durationMinutes <= 30 ? 'is-compact is-very-compact' : ($durationMinutes <= 45 ? 'is-compact' : ''));
    $clientName = trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? ''));
    $stylistName = $appointment->stylist?->name ?? $appointment->chair_station ?? 'Sin asignar';
    $hoverDetails = $appointment->trafficLightLabel()."\nCliente: ".$clientName."\nEspecialista: ".$stylistName;
@endphp

<a
    class="calendar-event {{ $sourceClass }} {{ $trafficClass }} {{ $durationClass }} {{ $pastClass }}"
    href="/citas/{{ $appointment->id }}/editar"
    title="{{ $hoverDetails }}"
    aria-label="{{ $hoverDetails }}"
    draggable="true"
    data-appointment-id="{{ $appointment->id }}"
    data-client-name="{{ $clientName }}"
    data-stylist-id="{{ $appointment->stylist_id }}"
    data-stylist-name="{{ $stylistName }}"
    data-appointment-status="{{ $appointment->status }}"
    data-start-at="{{ $startsAt->toIso8601String() }}"
    data-end-at="{{ $endsAt->toIso8601String() }}"
    data-duration-minutes="{{ $durationMinutes }}"
    data-move-url="/agenda/citas/{{ $appointment->id }}/mover"
    style="top: {{ $top }}px; height: {{ $height }}px;"
>
    <b>
        @if ($appointment->source === 'google_calendar')
            <img class="appointment-google-badge" src="/google-g.svg" alt="" title="Importada desde Google Calendar">
        @endif
        {{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}
    </b>
    <span>{{ $startsAt->format('g:i A') }} - {{ $endsAt->format('g:i A') }}</span>
    <small>{{ $clientName }} - {{ $stylistName }}</small>
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
</a>
