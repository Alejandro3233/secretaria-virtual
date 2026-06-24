@extends('public.bookings.layout')

@section('title', ($accepted ? 'Cita adelantada' : 'Horario no disponible').' - Secretary365')

@section('content')
    <section class="hero">
        <h1>{{ $accepted ? 'Cita adelantada' : 'Horario no disponible' }}</h1>
        <p>{{ $message }}</p>
    </section>
    <section class="card">
        <b>{{ $appointment->clinic->name }}</b>
        <p class="subtitle">{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</p>
        <p class="subtitle">{{ $appointment->starts_at->timezone($appointment->clinic->localTimezone())->format('d/m/Y g:i A') }}</p>
    </section>

    @if (! $accepted && ($alternative ?? null) && ($alternativeUrl ?? null))
        <section class="card" style="border-color:#bfdbfe;background:#eff6ff;">
            <h2 style="margin-top:0;">Encontramos otro horario disponible</h2>
            <p>
                Podemos adelantar tu cita a las
                <strong>{{ $alternative['proposed_start']->format('g:i A') }}</strong>
                del {{ $alternative['proposed_start']->format('d/m/Y') }}.
            </p>
            <a class="btn primary" href="{{ $alternativeUrl }}">Revisar nuevo horario</a>
        </section>
    @elseif (! $accepted)
        <section class="card">
            <p style="margin:0;">Tu cita original sigue reservada. En este momento no hay otro hueco compatible.</p>
        </section>
    @endif
@endsection
