@extends('public.bookings.layout')

@section('title', $clinic->name.' - Secretaria Virtual')

@section('content')
    <section class="hero">
        <span class="status">Salon disponible</span>
        <h1 style="margin-top:14px;">{{ $clinic->name }}</h1>
        <p>{{ $clinic->address ?? 'Agenda disponible para reservas en linea.' }}</p>
        <div class="actions">
            <a class="btn primary" href="/salones/{{ $clinic->id }}/reservar">Reservar cita</a>
            <a class="btn" href="/particular">Buscar otro salon</a>
        </div>
    </section>

    <section class="grid-2">
        <article class="card">
            <h2>Servicios</h2>
            <div class="list" style="margin-top:14px;">
                @forelse ($services as $service)
                    <div class="item">
                        <div>
                            <b>{{ $service->name }}</b>
                            <span>{{ $service->duration_minutes }} min{{ $service->price_cents !== null ? ' - $'.number_format($service->price_cents / 100, 2) : '' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="subtitle">Este salon aun no tiene servicios publicados.</p>
                @endforelse
            </div>
        </article>

        <article class="card">
            <h2>Equipo</h2>
            <div class="list" style="margin-top:14px;">
                @forelse ($stylists as $stylist)
                    <div class="item">
                        <div>
                            <b>{{ $stylist->name }}</b>
                            <span>{{ $stylist->specialty ?? 'Especialista del salon' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="subtitle">Puedes reservar sin elegir especialista.</p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
