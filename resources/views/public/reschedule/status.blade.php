@extends('public.bookings.layout')

@section('title', $title.' - Secretaria Virtual')

@section('content')
    <section class="hero">
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </section>

    <section class="card">
        <div class="grid-3">
            <div>
                <b>Servicio</b>
                <p class="subtitle">{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</p>
            </div>
            <div>
                <b>Fecha y hora</b>
                <p class="subtitle">{{ $appointment->starts_at->timezone($appointment->clinic->localTimezone())->format('d/m/Y g:i A') }}</p>
            </div>
            <div>
                <b>Estado</b>
                <p class="subtitle">
                    @if ($appointment->status === 'confirmed')
                        Confirmada
                    @elseif (in_array($appointment->status, ['cancelled', 'canceled'], true))
                        Cancelada
                    @else
                        Pendiente
                    @endif
                </p>
            </div>
        </div>
    </section>

    <section class="card">
        <b>Salon</b>
        <p class="subtitle">{{ $appointment->clinic->name }}</p>
        @if ($appointment->clinic->address)
            <p class="subtitle">{{ $appointment->clinic->address }}</p>
        @endif
    </section>
@endsection
