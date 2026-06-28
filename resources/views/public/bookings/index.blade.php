@extends('public.bookings.layout')

@section('title', 'Buscar salon - Secretary365')

@section('content')
    <section class="hero">
        <h1>Reserva una cita en tu salon</h1>
        <p>Busca el salon, elige el servicio y selecciona un horario disponible sin entrar al panel administrativo.</p>
    </section>

    <section class="card">
        <form method="GET" action="/particular" class="grid-2" style="align-items:end;">
            <div>
                <label for="q">Nombre, telefono o direccion del salon</label>
                <input id="q" name="q" value="{{ $query }}" placeholder="Ej. Salon Sofia, +1 555, Miami">
            </div>
            <button class="btn primary" type="submit">Buscar salon</button>
        </form>
    </section>

    <section class="list">
        @forelse ($clinics as $clinic)
            <article class="item">
                <div>
                    <b>{{ $clinic->name }}</b>
                    <span>{{ $clinic->address ?? 'Direccion pendiente' }}</span>
                    <span>{{ $clinic->phone ?? $clinic->email ?? 'Contacto pendiente' }}</span>
                </div>
                <div class="actions">
                    <a class="btn" href="/salones/{{ $clinic->id }}">Ver salon</a>
                    <a class="btn primary" href="/salones/{{ $clinic->id }}/reservar">Reservar</a>
                </div>
            </article>
        @empty
            <article class="card">
                <b>No encontramos salones</b>
                <p class="subtitle">Prueba con otro nombre, telefono o ciudad.</p>
            </article>
        @endforelse
    </section>
@endsection
