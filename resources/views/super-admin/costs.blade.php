@extends('layouts.app')

@section('title', 'Costos por salon - Secretary365')
@section('page_title', 'Costos por salon')
@section('page_subtitle', 'Consumo estimado de comunicaciones, peticiones y recursos por negocio.')

@section('content')
    <style>
        .cost-shell { display: grid; gap: 18px; }
        .cost-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 15px 18px; }
        .cost-toolbar form { display: flex; gap: 8px; align-items: center; }
        .cost-toolbar b, .cost-toolbar span { display: block; }
        .cost-toolbar span { margin-top: 4px; color: var(--muted); font-size: 13px; }
        .cost-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .cost-kpi { min-height: 112px; padding: 15px; border: 1px solid #f0e5eb; border-radius: 8px; background: #fffafd; }
        .cost-kpi span, .cost-kpi b, .cost-kpi small { display: block; }
        .cost-kpi span { color: var(--muted); font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .cost-kpi b { margin-top: 10px; font-size: 25px; line-height: 1; }
        .cost-kpi small { margin-top: 9px; color: var(--muted); line-height: 1.35; }
        .cost-grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 18px; align-items: start; }
        .cost-note { display: grid; gap: 10px; }
        .cost-note-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1e7ec; }
        .cost-note-row:last-child { border-bottom: 0; }
        .cost-note-row span { color: var(--muted); }
        .cost-note-row b { white-space: nowrap; }
        .cost-table { width: 100%; border-collapse: collapse; }
        .cost-table th, .cost-table td { padding: 12px 10px; border-bottom: 1px solid #f1e7ec; text-align: left; vertical-align: middle; }
        .cost-table th { color: var(--muted); font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .cost-table td { font-size: 13px; }
        .cost-table td.numeric, .cost-table th.numeric { text-align: right; }
        .cost-table b, .cost-table span { display: block; }
        .cost-table span { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .cost-total { color: #166534; font-weight: 900; }
        .table-scroll { overflow-x: auto; }
        @media (max-width: 980px) { .cost-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .cost-grid { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .cost-toolbar { align-items: stretch; flex-direction: column; } .cost-toolbar form { width: 100%; } .cost-toolbar input { flex: 1; } .cost-kpis { grid-template-columns: 1fr; } .cost-table { min-width: 900px; } }
    </style>

    @php
        $money = fn (float $amount): string => '$'.number_format($amount, 4);
        $moneyTotal = fn (float $amount): string => '$'.number_format($amount, 2);
        $bytes = function (int $value): string {
            if ($value >= 1073741824) return number_format($value / 1073741824, 2).' GB';
            if ($value >= 1048576) return number_format($value / 1048576, 2).' MB';
            if ($value >= 1024) return number_format($value / 1024, 2).' KB';
            return $value.' B';
        };
        $processingTime = fn (int $milliseconds): string => $milliseconds >= 1000
            ? number_format($milliseconds / 1000, 2).' s'
            : $milliseconds.' ms';
    @endphp

    <div class="cost-shell">
        <article class="card cost-toolbar">
            <div>
                <b>{{ ucfirst($selectedMonth->copy()->locale('es')->isoFormat('MMMM [de] YYYY')) }}</b>
                <span>Los importes son estimados segun los costos configurados en produccion.</span>
            </div>
            <form method="GET" action="{{ route('super-admin.costs') }}">
                <input type="month" name="month" value="{{ $selectedMonth->format('Y-m') }}" aria-label="Mes">
                <button class="btn primary" type="submit">Ver mes</button>
            </form>
        </article>

        <section class="cost-kpis" aria-label="Resumen de costos">
            <article class="cost-kpi">
                <span>Total hoy</span>
                <b>{{ $moneyTotal($summary['day']['total_cost']) }}</b>
                <small>{{ $summary['day']['sms_count'] }} SMS, {{ $summary['day']['call_count'] }} llamadas, {{ $summary['day']['email_count'] }} correos.</small>
            </article>
            <article class="cost-kpi">
                <span>Total mes</span>
                <b>{{ $moneyTotal($summary['month']['total_cost']) }}</b>
                <small>{{ $clinics->count() }} salon{{ $clinics->count() === 1 ? '' : 'es' }} incluidos.</small>
            </article>
            <article class="cost-kpi">
                <span>Comunicaciones mes</span>
                <b>{{ $moneyTotal($summary['month']['sms_cost'] + $summary['month']['call_cost'] + $summary['month']['email_cost']) }}</b>
                <small>SMS, llamadas y correos registrados por el sistema.</small>
            </article>
            <article class="cost-kpi">
                <span>Maquina mes</span>
                <b>{{ $moneyTotal($summary['month']['machine_cost']) }}</b>
                <small>{{ $hasUsageMetrics ? 'Repartido proporcionalmente según el consumo medido.' : 'Sin mediciones aún; repartido por igual.' }}</small>
            </article>
        </section>

        <div class="cost-grid">
            <article class="card">
                <div class="table-scroll">
                    <table class="cost-table">
                        <thead>
                            <tr>
                                <th>Salon</th>
                                <th class="numeric">SMS hoy</th>
                                <th class="numeric">Llamadas hoy</th>
                                <th class="numeric">Correos hoy</th>
                                <th class="numeric">Costo hoy</th>
                                <th class="numeric">SMS mes</th>
                                <th class="numeric">Llamadas mes</th>
                                <th class="numeric">Correos mes</th>
                                <th class="numeric">Maquina mes</th>
                                <th class="numeric">Total mes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($clinics as $row)
                                <tr>
                                    <td>
                                        <b>{{ $row['clinic']->name }}</b>
                                        <span>{{ $row['clinic']->email ?: 'Sin correo' }}</span>
                                        <span>{{ number_format($row['month']['usage']['requests']) }} peticiones · {{ $row['month']['usage']['active_minutes'] }} min activos</span>
                                        <span>RAM {{ $bytes($row['month']['usage']['memory_bytes']) }} · Disco/datos {{ $bytes($row['month']['usage']['disk_bytes']) }} · Proceso {{ $processingTime($row['month']['usage']['duration_ms']) }}</span>
                                    </td>
                                    <td class="numeric">{{ $row['day']['sms_count'] }}</td>
                                    <td class="numeric">{{ $row['day']['call_count'] }}</td>
                                    <td class="numeric">{{ $row['day']['email_count'] }}</td>
                                    <td class="numeric cost-total">{{ $moneyTotal($row['day']['total_cost']) }}</td>
                                    <td class="numeric">{{ $row['month']['sms_count'] }}<span>{{ $moneyTotal($row['month']['sms_cost']) }}</span></td>
                                    <td class="numeric">{{ $row['month']['call_count'] }}<span>{{ $moneyTotal($row['month']['call_cost']) }}</span></td>
                                    <td class="numeric">{{ $row['month']['email_count'] }}<span>{{ $moneyTotal($row['month']['email_cost']) }}</span></td>
                                    <td class="numeric">{{ $moneyTotal($row['month']['machine_cost']) }}</td>
                                    <td class="numeric cost-total">{{ $moneyTotal($row['month']['total_cost']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">Todavia no hay salones registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="card cost-note">
                <div>
                    <h2 style="font-size:17px;margin-bottom:4px;">Costos usados</h2>
                    <span class="subtitle">Puedes cambiarlos desde el archivo .env.</span>
                </div>
                <div class="cost-note-row"><span>Cada SMS</span><b>{{ $money($unitCosts['sms']) }}</b></div>
                <div class="cost-note-row"><span>Cada llamada</span><b>{{ $money($unitCosts['call']) }}</b></div>
                <div class="cost-note-row"><span>Cada correo</span><b>{{ $money($unitCosts['email']) }}</b></div>
                <div class="cost-note-row"><span>Maquina mensual total</span><b>{{ $moneyTotal($machineMonthlyUsd) }}</b></div>
                <div class="cost-note-row"><span>Reparto de maquina</span><b>{{ $hasUsageMetrics ? 'Por consumo' : 'Por igual' }}</b></div>
                <p class="subtitle" style="line-height:1.5;">RAM, disco/datos, tiempo de proceso y actividad son aproximaciones registradas desde ahora. El costo seguirá en $0.00 hasta configurar <code>SV_COST_MACHINE_MONTHLY_USD</code>. Las llamadas se cuentan como eventos.</p>
            </aside>
        </div>
    </div>
@endsection
