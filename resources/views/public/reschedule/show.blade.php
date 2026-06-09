@extends('public.bookings.layout')

@section('title', 'Reagendar cita - Secretaria Virtual')

@section('content')
    <section class="hero">
        <h1>Reagendar cita en {{ $appointment->clinic->name }}</h1>
        <p>{{ $appointment->client->first_name }}, elige un nuevo horario disponible para tu cita.</p>
    </section>

    @if (session('reschedule_status'))
        <section class="card notice">
            {{ session('reschedule_status') }}
        </section>
    @endif

    @if ($errors->any())
        <section class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:900;">
            Selecciona un horario disponible para continuar.
        </section>
    @endif

    <section class="card">
        <div class="grid-3">
            <div>
                <b>Servicio</b>
                <p class="subtitle">{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</p>
            </div>
            <div>
                <b>Cita actual</b>
                <p class="subtitle">{{ $appointment->starts_at->timezone($appointment->clinic->localTimezone())->format('d/m/Y g:i A') }}</p>
            </div>
            <div>
                <b>Salon</b>
                <p class="subtitle">{{ $appointment->clinic->address ?? $appointment->clinic->name }}</p>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('public-reschedule.update', [$appointment, $token]) }}" class="card">
        @csrf

        <section>
            <h2>Fecha</h2>
            <div class="slots" style="margin-top:12px;">
                @foreach ($dates as $date)
                    <a class="btn {{ $date->isSameDay($selectedDate) ? 'primary' : '' }}" href="{{ route('public-reschedule.show', [$appointment, $token, 'date' => $date->format('Y-m-d')]) }}">
                        {{ ucfirst($date->locale('es')->isoFormat('ddd D MMM')) }}
                    </a>
                @endforeach
            </div>
        </section>

        <section>
            <h2>Horario disponible</h2>
            <p class="subtitle">Los horarios se calculan con la agenda real y la jornada de los especialistas.</p>
            <div class="slots" style="margin-top:12px;">
                @forelse ($availableSlots as $slot)
                    <label class="slot">
                        <input type="radio" name="slot" value="{{ $slot['value'] }}" required>
                        <span>{{ $slot['starts_at']->format('g:i A') }} - {{ $slot['stylist']->name }}</span>
                    </label>
                @empty
                    <p class="subtitle">No hay horarios disponibles para esta fecha. Prueba con otro dia.</p>
                @endforelse
            </div>
            @error('slot') <div class="error">{{ $message }}</div> @enderror
        </section>

        <button class="btn primary" type="submit" @disabled($availableSlots->isEmpty())>Confirmar nuevo horario</button>
    </form>
@endsection
