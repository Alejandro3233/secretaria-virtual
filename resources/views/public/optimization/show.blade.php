@extends('public.bookings.layout')

@section('title', 'Adelantar cita - Secretary365')

@section('content')
    <section class="hero">
        <h1>Tenemos un horario más temprano</h1>
        <p>{{ $appointment->client?->first_name ?: 'Hola' }}, confirma si deseas adelantar tu cita en {{ $appointment->clinic->name }}.</p>
    </section>

    <section class="card">
        <div class="grid-3">
            <div><b>Servicio</b><p class="subtitle">{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</p></div>
            <div><b>Horario actual</b><p class="subtitle">{{ $appointment->starts_at->timezone($appointment->clinic->localTimezone())->format('d/m/Y g:i A') }}</p></div>
            <div><b>Nuevo horario</b><p class="subtitle">{{ $target->format('d/m/Y g:i A') }}</p></div>
        </div>
        <p class="subtitle" style="margin-bottom:0;">Profesional: {{ $targetStylist->name }}</p>
    </section>

    <form method="POST" action="{{ request()->fullUrl() }}" class="card">
        @csrf
        <p style="margin-top:0;">Tu cita original se mantendrá sin cambios hasta que confirmes.</p>
        <button class="btn primary" type="submit">Aceptar el horario más temprano</button>
    </form>
@endsection
