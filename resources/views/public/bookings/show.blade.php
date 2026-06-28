@extends('public.bookings.layout')

@section('title', $clinic->name.' - Secretary365')

@section('content')
    <style>.team-avatar{width:48px;height:48px;display:grid;place-items:center;flex:0 0 48px;overflow:hidden;border-radius:50%;background:#f3e8ee;color:#c0265a;font-weight:900}.team-avatar img{width:100%;height:100%;object-fit:cover}.team-person,.public-service{display:flex;align-items:center;gap:11px}.public-service-image{width:68px;height:68px;display:grid;place-items:center;flex:0 0 68px;overflow:hidden;border-radius:10px;background:#f3e8ee;color:#c0265a;font-size:11px;font-weight:900;text-align:center}.public-service-image img{width:100%;height:100%;object-fit:cover}</style>
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
                        <div class="public-service">
                            <span class="public-service-image">@if($service->imageUrl())<img src="{{ $service->imageUrl() }}" alt="Resultado de {{ $service->name }}">@else{{ mb_strtoupper(mb_substr($service->name, 0, 2)) }}@endif</span>
                            <div>
                            <b>{{ $service->name }}</b>
                            <span>{{ $service->duration_minutes }} min{{ $service->price_cents !== null ? ' - $'.number_format($service->price_cents / 100, 2) : '' }}</span>
                            @if($service->activeAddons->isNotEmpty())<small>Extras disponibles desde +{{ number_format($service->activeAddons->min('price_cents') / 100, 2) }}</small>@endif
                            </div>
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
                        <div class="team-person">
                            <span class="team-avatar">@if($stylist->avatarUrl())<img src="{{ $stylist->avatarUrl() }}" alt="Foto de {{ $stylist->name }}">@else{{ $stylist->initials() }}@endif</span>
                            <div>
                            <b>{{ $stylist->name }}</b>
                            <span>{{ $stylist->specialty ?? 'Especialista del salon' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="subtitle">Puedes reservar sin elegir especialista.</p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
