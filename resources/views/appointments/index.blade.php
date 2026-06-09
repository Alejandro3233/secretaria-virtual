@extends('layouts.app')

@section('title', 'Citas - Secretaria Virtual')
@section('page_title', 'Citas')
@section('page_subtitle', 'Gestiona reservas, confirmaciones, cambios y cancelaciones del salon.')
@section('page_actions')
    <a class="btn" href="/agenda">Ver agenda</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    <style>
        .appointment-filter {
            display: inline-grid;
            grid-template-columns: repeat(3, minmax(90px, 1fr));
            gap: 4px;
            padding: 4px;
            border: 1px solid #dde3ea;
            border-radius: 8px;
            background: #f8fafc;
        }

        .appointment-filter a {
            min-height: 36px;
            display: grid;
            place-items: center;
            padding: 0 14px;
            border-radius: 6px;
            color: #475569;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }

        .appointment-filter a.active {
            background: #10131a;
            color: #fff;
            box-shadow: 0 6px 16px rgba(15, 23, 42, .12);
        }

        @media (max-width: 620px) {
            .appointment-filter {
                width: 100%;
            }
        }

        .reminder-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 118px;
        }

        .reminder-toggle {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 900;
            color: #475569;
            white-space: nowrap;
        }

        .reminder-toggle input {
            width: 16px;
            height: 16px;
            accent-color: #c72664;
        }

        .reminder-toggle input:disabled + span {
            color: #94a3b8;
        }

    </style>

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
        <div class="section-title">
            <div>
                <h2>Listado de citas</h2>
                <span class="subtitle">
                    @if ($period === 'week')
                        Mostrando citas de esta semana.
                    @elseif ($period === 'month')
                        Mostrando citas de este mes.
                    @else
                        Mostrando todas las citas, de la mas reciente a la mas antigua.
                    @endif
                </span>
            </div>
            <nav class="appointment-filter" aria-label="Filtro de citas">
                <a class="{{ $period === 'all' ? 'active' : '' }}" href="/citas">Todas</a>
                <a class="{{ $period === 'week' ? 'active' : '' }}" href="/citas?period=week">Semana</a>
                <a class="{{ $period === 'month' ? 'active' : '' }}" href="/citas?period=month">Mes</a>
            </nav>
        </div>

        @if ($appointments->isEmpty())
            <div class="section-title" style="margin-bottom:0;">
                <div>
                    <h2>No hay citas para este filtro</h2>
                    <span class="subtitle">Cambia el filtro, crea una cita nueva o sincroniza Google Calendar para importar reservas.</span>
                </div>
                <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Telefono</th>
                        <th>Estilista</th>
                        <th>Tiempo</th>
                        <th>Recordatorio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($appointments as $appointment)
                        @php
                            $duration = $appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : null;
                            $clientPhone = trim((string) $appointment->client?->phone);
                            $clientPhoneLabel = $clientPhone !== '' && ! str_starts_with($clientPhone, 'google:') ? $clientPhone : 'Sin telefono';
                            $clientName = trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? ''));
                            $clientName = trim(preg_replace('/^Google Calendar\s*-\s*/i', '', $clientName));
                            $serviceName = trim((string) ($appointment->service?->name ?? $appointment->reason ?? 'Cita'));
                            $serviceName = strcasecmp($serviceName, 'Google Calendar') === 0 ? '' : preg_replace('/^Google Calendar\s*-\s*/i', '', $serviceName);
                            $canRemind = ! in_array($appointment->status, ['cancelled', 'canceled'], true) && $clientPhone !== '' && ! str_starts_with($clientPhone, 'google:');
                            $callEnabled = $appointment->reminder_call_enabled ?? false;
                            $smsEnabled = $appointment->reminder_sms_enabled ?? false;
                        @endphp
                        <tr>
                            <td>{{ $appointment->starts_at->format('M d, Y') }}<br>{{ $appointment->starts_at->format('g:i A') }}</td>
                            <td>
                                {{ $clientName ?: 'Cliente' }}<br>
                                {{ $serviceName ?: 'Cita' }}
                            </td>
                            <td>{{ $clientPhoneLabel }}</td>
                            <td>{{ $appointment->stylist?->name ?? 'Sin asignar' }}</td>
                            <td>{{ $duration ? $duration.' min' : 'Sin duracion' }}</td>
                            <td>
                                <form class="reminder-controls" method="POST" action="/citas/{{ $appointment->id }}/recordatorio">
                                    @csrf
                                    @method('PUT')
                                    <label class="reminder-toggle">
                                        <input type="hidden" name="reminder_call_enabled" value="0">
                                        <input type="checkbox" name="reminder_call_enabled" value="1" @checked($callEnabled) @disabled(! $canRemind) onchange="this.form.submit()">
                                        <span>Llamada</span>
                                    </label>
                                    <label class="reminder-toggle">
                                        <input type="hidden" name="reminder_sms_enabled" value="0">
                                        <input type="checkbox" name="reminder_sms_enabled" value="1" @checked($smsEnabled) @disabled(! $canRemind) onchange="this.form.submit()">
                                        <span>SMS</span>
                                    </label>
                                </form>
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
