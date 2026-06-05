@extends('layouts.app')

@section('title', 'Citas - Secretaria Virtual')
@section('page_title', 'Citas')
@section('page_subtitle', 'Gestiona reservas, confirmaciones, cambios y cancelaciones del salon.')
@section('page_actions')
    <a class="btn" href="/agenda">Ver agenda</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    @if (session('appointment_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('appointment_status') }}
        </div>
    @endif

    @if (session('appointment_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('appointment_error') }}
        </div>
    @endif

    <article class="card">
        @if ($appointments->isEmpty())
            <div class="section-title" style="margin-bottom:0;">
                <div>
                    <h2>No hay citas guardadas</h2>
                    <span class="subtitle">Crea una cita nueva o sincroniza Google Calendar para importar reservas.</span>
                </div>
                <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Estilista</th>
                        <th>Estacion</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($appointments as $appointment)
                        @php($duration = $appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : null)
                        <tr>
                            <td>{{ $appointment->starts_at->format('M d, Y') }}<br>{{ $appointment->starts_at->format('g:i A') }}</td>
                            <td>
                                {{ trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? '')) }}<br>
                                {{ $appointment->client?->phone ?? 'Sin telefono' }}
                            </td>
                            <td>{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}<br>{{ $duration ? $duration.' min' : 'Sin duracion' }}</td>
                            <td>{{ $appointment->stylist?->name ?? 'Sin asignar' }}</td>
                            <td>{{ $appointment->chair_station ?? 'Sin estacion' }}</td>
                            <td>
                                @if (in_array($appointment->status, ['confirmed'], true))
                                    <span class="status ok">Confirmada</span>
                                @elseif (in_array($appointment->status, ['cancelled', 'canceled'], true))
                                    <span class="status danger">Cancelada</span>
                                @else
                                    <span class="status wait">Pendiente</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn" href="/citas/{{ $appointment->id }}/editar">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </article>
@endsection
