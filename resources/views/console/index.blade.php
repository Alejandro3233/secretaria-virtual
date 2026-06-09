@extends('layouts.app')

@section('title', 'Consola - Secretaria Virtual')
@section('page_title', 'Consola principal')
@section('page_subtitle', auth()->user()->name.' - '.($clinic?->name ?? 'Salon sin configurar'))
@section('page_actions')
    <a class="btn" href="/agenda">Ver agenda</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    <section class="card" style="margin-bottom:18px;">
        <div class="section-title" style="margin-bottom:0;">
            <div>
                <span class="subtitle">Salon activo</span>
                <h2>{{ $clinic?->name ?? 'Salon sin configurar' }}</h2>
                @if ($clinic?->address)
                    <span class="subtitle">{{ $clinic->address }}</span>
                @endif
            </div>
            <span class="status {{ in_array($clinic?->subscription_status, ['active', 'trial'], true) ? 'ok' : 'wait' }}">
                {{ ucfirst($clinic?->subscription_status ?? 'pendiente') }}
            </span>
        </div>
    </section>

    <section class="grid-4" aria-label="Resumen operativo">
        <article class="card">
            <div class="metric-label">Citas de hoy</div>
            <div class="metric">{{ $todayAppointments->count() }}</div>
            <div class="trend">{{ $serviceBreakdown ?: 'Sin citas programadas para hoy' }}</div>
        </article>
        <article class="card">
            <div class="metric-label">Llamadas atendidas</div>
            <div class="metric">{{ $callsToday }}</div>
            <div class="trend">{{ $resolvedCallsToday }} resueltas con accion registrada</div>
        </article>
        <article class="card">
            <div class="metric-label">SMS enviados</div>
            <div class="metric">{{ $smsToday }}</div>
            <div class="trend">Confirmaciones y avisos enviados hoy</div>
        </article>
        <article class="card">
            <div class="metric-label">Huecos evitados</div>
            <div class="metric">{{ $savedSlotsToday }}</div>
            <div class="trend">Cancelaciones o reprogramaciones registradas hoy</div>
        </article>
    </section>

    <section class="grid-2" style="margin-top:18px;">
        <article class="card">
            <div class="section-title">
                <h2>Agenda inmediata</h2>
                <a class="btn" href="/agenda">Abrir agenda</a>
            </div>
            @if ($upcomingAppointments->isEmpty())
                <div class="section-title" style="margin-bottom:0;">
                    <div>
                        <h2>No hay citas proximas hoy</h2>
                        <span class="subtitle">Las siguientes citas del dia apareceran aqui automaticamente.</span>
                    </div>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Cliente</th>
                            <th>Servicio</th>
                            <th>Estilista</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($upcomingAppointments as $appointment)
                            @php($duration = $appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : null)
                            <tr>
                                <td>{{ $appointment->starts_at->format('g:i A') }}</td>
                                <td>
                                    {{ trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? '')) }}<br>
                                    <span class="subtitle">{{ $appointment->client?->phone ?? 'Sin telefono' }}</span>
                                </td>
                                <td>
                                    {{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}<br>
                                    <span class="subtitle">{{ $duration ? $duration.' min' : 'Sin duracion' }}</span>
                                </td>
                                <td>
                                    {{ $appointment->stylist?->name ?? 'Sin asignar' }}<br>
                                    <span class="subtitle">{{ $appointment->chair_station ?? 'Sin estacion' }}</span>
                                </td>
                                <td>
                                    @if ($appointment->status === 'confirmed')
                                        <span class="status ok">Confirmada</span>
                                    @elseif (in_array($appointment->status, ['cancelled', 'canceled'], true))
                                        <span class="status danger">Cancelada</span>
                                    @else
                                        <span class="status wait">{{ ucfirst($appointment->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </article>

        <div style="display:grid;gap:18px;">
            <article class="card integration-status-bar">
                <div class="section-title"><h2>Estado de integraciones</h2></div>
                <div class="item">
                    <div><b>Twilio SMS</b><span>{{ config('services.twilio.from') ? 'Numero configurado para mensajes salientes.' : 'Agrega TWILIO_FROM en .env.' }}</span></div>
                    <span class="status integration-status {{ config('services.twilio.from') ? 'ok' : 'wait' }}">{{ config('services.twilio.from') ? 'Activo' : 'Pendiente' }}</span>
                </div>
                <div class="item">
                    <div><b>Google Calendar</b><span>{{ $clinic?->google_calendar_summary ?? 'Calendario no conectado.' }}</span></div>
                    <span class="status integration-status {{ $clinic?->google_connected_at ? 'ok' : 'wait' }}">{{ $clinic?->google_connected_at ? 'Activo' : 'Pendiente' }}</span>
                </div>
                <div class="item">
                    <div><b>Voz Google TTS</b><span>{{ (config('google.tts.credentials_path') && is_file(config('google.tts.credentials_path'))) || config('google.tts.credentials_json') ? 'Voz configurada para la secretaria.' : 'Agrega GOOGLE_TTS_CREDENTIALS en .env.' }}</span></div>
                    <span class="status integration-status {{ (config('google.tts.credentials_path') && is_file(config('google.tts.credentials_path'))) || config('google.tts.credentials_json') ? 'ok' : 'wait' }}">{{ (config('google.tts.credentials_path') && is_file(config('google.tts.credentials_path'))) || config('google.tts.credentials_json') ? 'Activo' : 'Pendiente' }}</span>
                </div>
                <div class="item">
                    <div><b>Stripe</b><span>{{ $clinic?->plan?->name ? 'Plan '.$clinic->plan->name : 'Plan no asignado.' }}</span></div>
                    <span class="status integration-status {{ in_array($clinic?->subscription_status, ['active', 'trial'], true) ? 'ok' : 'wait' }}">{{ ucfirst($clinic?->subscription_status ?? 'pendiente') }}</span>
                </div>
                <div class="item">
                    <div><b>Email</b><span>{{ config('mail.default') === 'log' ? 'Los correos estan guardandose en logs.' : 'Mailer '.config('mail.default').' configurado.' }}</span></div>
                    <span class="status integration-status {{ config('mail.default') === 'log' ? 'wait' : 'ok' }}">{{ config('mail.default') === 'log' ? 'Log' : 'Activo' }}</span>
                </div>
            </article>

            <article class="card">
                <div class="section-title"><h2>Ultimas llamadas</h2></div>
                @forelse ($latestCalls as $call)
                    <div class="item">
                        <div>
                            <b>{{ $call->intent ? ucfirst(str_replace('_', ' ', $call->intent)) : 'Llamada registrada' }}</b>
                            <span>
                                {{ trim(($call->first_name ?? '').' '.($call->last_name ?? '')) ?: $call->from_phone }}
                                @if ($call->status)
                                    - {{ $call->status }}
                                @endif
                            </span>
                        </div>
                        <span>{{ \Illuminate\Support\Carbon::parse($call->created_at)->format('g:i') }}</span>
                    </div>
                @empty
                    <div class="item">
                        <div><b>Sin llamadas todavia</b><span>Las llamadas de Twilio apareceran aqui cuando se registren.</span></div>
                    </div>
                @endforelse
            </article>
        </div>
    </section>
@endsection
