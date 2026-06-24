@extends('public.bookings.layout')

@section('title', $title.' - Secretary365')

@section('page-styles')
        .appointment-result { min-height: calc(100vh - 75px); display: grid; place-items: center; padding: 48px 0; }
        .appointment-result-card { width: min(680px, 100%); overflow: hidden; border: 1px solid #eadde3; border-radius: 18px; background: #fff; box-shadow: 0 24px 70px rgba(46, 20, 31, .10); }
        .appointment-result-head { padding: 38px 34px 30px; text-align: center; background: linear-gradient(145deg, #fff7fa 0%, #fff 70%); }
        .result-icon { width: 72px; height: 72px; margin: 0 auto 20px; display: grid; place-items: center; border-radius: 50%; background: #dcfce7; color: #15803d; font-size: 34px; font-weight: 900; }
        .appointment-result.is-danger .result-icon { background: #fee2e2; color: #b91c1c; }
        .appointment-result-head h1 { margin: 0; font-size: clamp(30px, 6vw, 42px); line-height: 1.1; }
        .appointment-result-head > p { margin: 12px auto 0; max-width: 500px; color: #647084; font-size: 17px; line-height: 1.55; }
        .result-status { margin-top: 18px; display: inline-flex; align-items: center; gap: 7px; border-radius: 999px; padding: 7px 12px; background: #dcfce7; color: #166534; font-size: 13px; font-weight: 900; }
        .appointment-result.is-danger .result-status { background: #fee2e2; color: #991b1b; }
        .appointment-details { display: grid; grid-template-columns: repeat(2, 1fr); border-top: 1px solid #f0e5ea; }
        .appointment-detail { padding: 20px 24px; border-bottom: 1px solid #f0e5ea; }
        .appointment-detail:nth-child(odd) { border-right: 1px solid #f0e5ea; }
        .appointment-detail small { display: block; margin-bottom: 7px; color: #9b6679; font-size: 11px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .appointment-detail strong { display: block; font-size: 16px; line-height: 1.4; }
        .appointment-detail span { display: block; margin-top: 4px; color: #647084; line-height: 1.45; }
        .result-footer { padding: 22px 24px 26px; text-align: center; }
        .result-footer p { margin: 0 0 16px; color: #647084; }
        @media (max-width: 560px) {
            .appointment-result { padding: 24px 0; }
            .appointment-result-head { padding: 30px 20px 24px; }
            .appointment-details { grid-template-columns: 1fr; }
            .appointment-detail:nth-child(odd) { border-right: 0; }
        }
@endsection

@section('content')
    @php
        $isCancelled = in_array($appointment->status, ['cancelled', 'canceled'], true);
        $localStart = $appointment->starts_at->timezone($appointment->clinic->localTimezone());
    @endphp

    <div class="appointment-result {{ $isCancelled ? 'is-danger' : 'is-ok' }}">
        <section class="appointment-result-card">
            <div class="appointment-result-head">
                <div class="result-icon" aria-hidden="true">{{ $isCancelled ? '×' : '✓' }}</div>
                <h1>{{ $title }}</h1>
                <p>{{ $message }}</p>
                <span class="result-status">{{ $isCancelled ? 'Cita cancelada' : 'Cita confirmada' }}</span>
            </div>

            <div class="appointment-details">
                <div class="appointment-detail">
                    <small>Cliente</small>
                    <strong>{{ trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')) ?: 'Cliente' }}</strong>
                </div>
                <div class="appointment-detail">
                    <small>Servicio</small>
                    <strong>{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita' }}</strong>
                </div>
                <div class="appointment-detail">
                    <small>Fecha</small>
                    <strong>{{ ucfirst($localStart->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')) }}</strong>
                </div>
                <div class="appointment-detail">
                    <small>Hora</small>
                    <strong>{{ $localStart->format('g:i A') }}</strong>
                    @if ($appointment->stylist)
                        <span>Con {{ $appointment->stylist->name }}</span>
                    @endif
                </div>
                <div class="appointment-detail" style="grid-column: 1 / -1; border-right: 0;">
                    <small>Salón</small>
                    <strong>{{ $appointment->clinic->name }}</strong>
                    @if ($appointment->clinic->address)
                        <span>{{ $appointment->clinic->address }}</span>
                    @endif
                </div>
            </div>

            <div class="result-footer">
                <p>{{ $isCancelled ? 'Puedes contactar con el salón si deseas reservar una nueva cita.' : 'Te esperamos. Guarda esta información para recordar tu cita.' }}</p>
                <a class="btn primary" href="/">Finalizar</a>
            </div>
        </section>
    </div>
@endsection
