@extends('layouts.app')

@section('title', 'Buscar - Secretary365')
@section('page_title', 'Buscar')
@section('page_subtitle', $query !== '' ? 'Resultados para "'.$query.'".' : 'Busca citas, clientes, servicios o estilistas.')

@section('content')
    <form class="card" method="GET" action="/buscar" style="margin-bottom:18px;">
        <label>Busqueda global</label>
        <div class="toolbar" style="grid-template-columns:1fr 140px;margin-bottom:0;">
            <input name="q" value="{{ $query }}" placeholder="Cliente, telefono, servicio, estilista...">
            <button class="btn primary" type="submit">Buscar</button>
        </div>
    </form>

    @if ($query === '')
        <section class="card">
            <div class="section-title" style="margin-bottom:0;">
                <div>
                    <h2>Empieza escribiendo una busqueda</h2>
                    <span class="subtitle">Puedes buscar por nombre de cliente, telefono, servicio, estilista, estado o estacion.</span>
                </div>
            </div>
        </section>
    @elseif ($appointments->isEmpty() && $services->isEmpty() && $stylists->isEmpty())
        <section class="card">
            <div class="section-title" style="margin-bottom:0;">
                <div>
                    <h2>No encontramos resultados</h2>
                    <span class="subtitle">Prueba con otro nombre, telefono o servicio.</span>
                </div>
            </div>
        </section>
    @else
        @if ($appointments->isNotEmpty())
            <article class="card" style="margin-bottom:18px;">
                <div class="section-title">
                    <h2>Citas</h2>
                    <span class="subtitle">{{ $appointments->count() }} resultados</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Servicio</th>
                            <th>Estilista</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            <tr>
                                <td>{{ $appointment->starts_at->format('M d, Y') }}<br>{{ $appointment->starts_at->format('g:i A') }}</td>
                                <td>{{ trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? '')) }}<br>{{ $appointment->client?->phone ?? 'Sin telefono' }}</td>
                                <td>{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</td>
                                <td>{{ $appointment->stylist?->name ?? 'Sin asignar' }}</td>
                                <td><a class="btn" href="/citas/{{ $appointment->id }}/editar">Abrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </article>
        @endif

        <section class="grid-2">
            <article class="card">
                <div class="section-title">
                    <h2>Servicios</h2>
                    <span class="subtitle">{{ $services->count() }} resultados</span>
                </div>
                @forelse ($services as $service)
                    <div class="item">
                        <div>
                            <b>{{ $service->name }}</b>
                            <span>{{ $service->duration_minutes }} min · ${{ number_format((float) $service->price, 2) }}</span>
                        </div>
                    </div>
                @empty
                    <span class="subtitle">Sin servicios encontrados.</span>
                @endforelse
            </article>

            <article class="card">
                <div class="section-title">
                    <h2>Estilistas</h2>
                    <span class="subtitle">{{ $stylists->count() }} resultados</span>
                </div>
                @forelse ($stylists as $stylist)
                    <div class="item">
                        <div>
                            <b>{{ $stylist->name }}</b>
                            <span>{{ $stylist->specialty ?? 'Sin especialidad' }}</span>
                        </div>
                    </div>
                @empty
                    <span class="subtitle">Sin estilistas encontrados.</span>
                @endforelse
            </article>
        </section>
    @endif
@endsection
