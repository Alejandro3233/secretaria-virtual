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
                <h2>Historial de llamadas</h2>
                <a class="btn" href="/citas">Ver citas</a>
            </div>
            @if ($latestCalls->isEmpty())
                <div class="section-title" style="margin-bottom:0;">
                    <div>
                        <h2>Sin llamadas registradas</h2>
                        <span class="subtitle">Las llamadas recordatorio apareceran aqui cuando Twilio las procese.</span>
                    </div>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Telefono</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latestCalls as $call)
                            @php
                                $clientName = trim(($call->first_name ?? '').' '.($call->last_name ?? '')) ?: 'Cliente';
                                $callLabel = match ($call->event) {
                                    'appointment_reminder_call' => 'Recordatorio',
                                    default => ucfirst(str_replace('_', ' ', (string) $call->event)),
                                };
                                $statusLabel = match ($call->status) {
                                    'queued' => 'En cola',
                                    'sent', 'completed', 'answered' => 'Completada',
                                    'failed' => 'Fallida',
                                    'busy' => 'Ocupado',
                                    'no-answer' => 'Sin respuesta',
                                    default => ucfirst((string) $call->status),
                                };
                                $statusClass = match ($call->status) {
                                    'sent', 'queued', 'completed', 'answered' => 'ok',
                                    'failed', 'busy', 'no-answer' => 'danger',
                                    default => 'wait',
                                };
                                $callDate = $call->sent_at ?: $call->created_at;
                            @endphp
                            <tr>
                                <td>
                                    {{ $clientName }}<br>
                                    <span class="subtitle">
                                        @if ($call->appointment_starts_at)
                                            Cita {{ \Illuminate\Support\Carbon::parse($call->appointment_starts_at)->format('d/m/Y g:i A') }}
                                        @else
                                            Sin cita asociada
                                        @endif
                                    </span>
                                </td>
                                <td>{{ $call->recipient ?: $call->client_phone ?: 'Sin telefono' }}</td>
                                <td>
                                    {{ $callLabel }}<br>
                                    @if ($call->error)
                                        <span class="subtitle">{{ \Illuminate\Support\Str::limit($call->error, 46) }}</span>
                                    @else
                                        <span class="subtitle">{{ $call->provider_message_id ?: 'Sin ID proveedor' }}</span>
                                    @endif
                                </td>
                                <td><span class="status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse($callDate)->format('d/m g:i A') }}
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
                @forelse ($latestInboundCalls as $call)
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
