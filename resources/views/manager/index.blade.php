@extends('layouts.app')

@section('title', 'Manager - Secretary365')
@section('page_title', 'Manager')
@section('page_subtitle', 'Informes claros para tomar mejores decisiones en el negocio.')

@section('content')
    <style>
        .manager-shell { display: grid; gap: 18px; }
        .manager-period { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 15px 18px; }
        .manager-period-copy b, .manager-period-copy span { display: block; }
        .manager-period-copy b { font-size: 16px; }
        .manager-period-copy span { margin-top: 4px; color: var(--muted); font-size: 13px; }
        .manager-period form { display: flex; align-items: center; gap: 8px; }
        .manager-period input { min-height: 38px; }
        .manager-head { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, 290px); gap: 16px; align-items: center; }
        .manager-head h2 { font-size: 20px; }
        .manager-search { min-height: 46px; display: grid; grid-template-columns: 20px 1fr; align-items: center; gap: 12px; border: 1px solid var(--line); border-radius: 8px; padding: 0 14px; background: white; }
        .manager-search:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(192,38,90,.1); }
        .manager-search input { min-height: 42px; border: 0; padding: 0; outline: 0; font-weight: 800; }
        .manager-grid { display: grid; grid-template-columns: repeat(2, minmax(260px, 1fr)); gap: 18px; }
        .manager-card { min-height: 112px; display: grid; grid-template-columns: 36px minmax(0,1fr) auto; gap: 13px; align-items: start; width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 18px; background: white; color: inherit; font: inherit; text-align: left; cursor: pointer; box-shadow: 0 1px 4px rgba(24,18,22,.06); }
        .manager-card:hover, .manager-card.active { border-color: #d7c4ce; box-shadow: 0 10px 24px rgba(24,18,22,.08); }
        .manager-card.active { outline: 2px solid rgba(192,38,90,.12); }
        .manager-card-icon { width: 32px; height: 32px; display: grid; place-items: center; color: var(--brand); }
        .manager-card b, .manager-card span { display: block; }
        .manager-card b { margin-top: 3px; font-size: 16px; }
        .manager-card span { margin-top: 7px; color: var(--muted); line-height: 1.45; }
        .manager-arrow { color: #a3919b; font-size: 20px; }
        .manager-panel { display: none; }
        .manager-shell.is-detail .manager-panel.active { display: block; }
        .manager-shell.is-detail .manager-head, .manager-shell.is-detail .manager-grid, .manager-shell.is-detail .manager-empty { display: none; }
        .manager-detail-head { display: none; align-items: center; gap: 10px; min-height: 42px; }
        .manager-shell.is-detail .manager-detail-head { display: flex; }
        .manager-detail-head h2 { margin: 0; font-size: 20px; }
        .manager-back { width: 34px; height: 34px; display: grid; place-items: center; border: 0; border-radius: 8px; background: transparent; cursor: pointer; }
        .manager-back:hover { background: #f3eef2; }
        .manager-empty { display: none; border: 1px dashed var(--line); border-radius: 8px; padding: 22px; background: white; color: var(--muted); font-weight: 800; }
        .manager-empty.active { display: block; }
        .report-stack { display: grid; gap: 18px; }
        .report-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; }
        .report-kpi { min-height: 112px; padding: 15px; border: 1px solid #f0e5eb; border-radius: 8px; background: #fffafd; }
        .report-kpi span, .report-kpi b, .report-kpi small { display: block; }
        .report-kpi span { color: var(--muted); font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .report-kpi b { margin-top: 10px; font-size: 25px; line-height: 1; }
        .report-kpi small { margin-top: 9px; color: var(--muted); line-height: 1.35; }
        .report-kpi.good { border-color: #bbf7d0; background: #f0fdf4; }
        .report-kpi.warn { border-color: #fde68a; background: #fffbeb; }
        .report-grid-2 { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px; }
        .report-card-title { display: flex; justify-content: space-between; gap: 12px; align-items: start; margin-bottom: 16px; }
        .report-card-title h2 { font-size: 17px; }
        .report-card-title span { color: var(--muted); font-size: 12px; }
        .trend-chart { min-height: 210px; display: grid; grid-template-columns: repeat(6,1fr); gap: 12px; align-items: end; padding-top: 20px; }
        .trend-column { min-width: 0; text-align: center; }
        .trend-value { display: block; margin-bottom: 7px; font-size: 10px; font-weight: 900; white-space: nowrap; }
        .trend-bar-wrap { height: 135px; display: flex; align-items: end; justify-content: center; border-bottom: 1px solid var(--line); }
        .trend-bar { width: min(30px,70%); min-height: 4px; border-radius: 5px 5px 0 0; background: linear-gradient(180deg,#dd4c7d,var(--brand)); }
        .trend-label { display: block; margin-top: 7px; color: var(--muted); font-size: 11px; font-weight: 800; }
        .insight-list { display: grid; gap: 10px; }
        .insight { display: grid; grid-template-columns: 34px minmax(0,1fr); gap: 11px; align-items: center; padding: 12px; border: 1px solid #f0e5eb; border-radius: 8px; background: #fffafd; }
        .insight-icon { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 50%; background: #fce7ef; color: var(--brand); font-weight: 900; }
        .insight b, .insight span { display: block; }
        .insight span { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 12px 10px; border-bottom: 1px solid #f1e7ec; text-align: left; vertical-align: middle; }
        .report-table th { color: var(--muted); font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .report-table td { font-size: 13px; }
        .report-table td:last-child, .report-table th:last-child { text-align: right; }
        .person-name { display: flex; align-items: center; gap: 9px; font-weight: 900; }
        .person-avatar { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 50%; background: #f3e8ee; color: var(--brand); font-size: 11px; }
        .stock-status { display: inline-flex; border-radius: 999px; padding: 4px 8px; background: #dcfce7; color: #166534; font-size: 10px; font-weight: 900; }
        .stock-status.low { background: #fef3c7; color: #92400e; }
        .stock-status.out { background: #fee2e2; color: #991b1b; }
        .inventory-form { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 12px; }
        .inventory-form .span-2 { grid-column: span 2; }
        .inventory-form label { display: block; margin-bottom: 6px; color: var(--muted); font-size: 11px; font-weight: 900; }
        .stock-adjust { display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px; }
        .stock-adjust input { width: 72px; min-height: 32px; padding: 5px 7px; }
        .stock-adjust button { min-height: 32px; padding: 0 9px; }
        .empty-report { padding: 26px; border: 1px dashed var(--line); border-radius: 8px; color: var(--muted); text-align: center; background: #fffafd; }
        @media (max-width: 980px) { .report-kpis { grid-template-columns: repeat(2,1fr); } .inventory-form { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 700px) { .manager-period, .manager-head { align-items: stretch; flex-direction: column; grid-template-columns: 1fr; } .manager-grid, .report-grid-2, .report-kpis { grid-template-columns: 1fr; } .manager-period form { width: 100%; } .manager-period input { flex: 1; } .inventory-form { grid-template-columns: 1fr; } .inventory-form .span-2 { grid-column: auto; } .report-table { min-width: 650px; } .table-scroll { overflow-x: auto; } }
    </style>

    @if (session('manager_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">{{ session('manager_status') }}</div>
    @endif
    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">{{ $errors->first() }}</div>
    @endif

    <article class="card manager-period">
        <div class="manager-period-copy">
            <b>{{ ucfirst($selectedMonth->copy()->locale('es')->isoFormat('MMMM [de] YYYY')) }}</b>
            <span>Los informes se actualizan con los datos registrados en Secretary365.</span>
        </div>
        <form method="GET" action="/manager">
            <input type="month" name="month" value="{{ $selectedMonth->format('Y-m') }}" aria-label="Mes del informe">
            <button class="btn" type="submit">Ver período</button>
        </form>
    </article>

    <div class="manager-shell">
        <div class="manager-head">
            <div><h2>Centro de informes</h2><span class="subtitle">Elige el área que quieres analizar.</span></div>
            <label class="manager-search"><svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><input id="manager-search" type="search" placeholder="Buscar informe" autocomplete="off"></label>
        </div>

        <section class="manager-grid" aria-label="Informes de Manager">
            @foreach ([
                ['rendimiento','Análisis de rendimiento','Ingresos, ocupación, tendencia y oportunidades.','M4 19V9M10 19V5M16 19v-7M22 19V3M2 19h22'],
                ['empresa','Informe de empresa','Una fotografía completa de la salud del negocio.','M3 21h18M5 21V7l7-4 7 4v14M9 21v-8h6v8'],
                ['facturacion','Facturación','Ingresos previstos, ticket medio y servicios.','M6 2h12v20l-3-2-3 2-3-2-3 2V2zM9 7h6M9 11h6M9 15h4'],
                ['profesionales','Informe de profesionales','Resultados individuales de cada empleado.','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M22 21v-2a4 4 0 0 0-3-3.87'],
                ['clientes','Informe de clientes','Fidelidad, recurrencia y clientes destacados.','M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M23 21v-2a4 4 0 0 0-3-3.87'],
                ['almacen','Informe de almacén','Valor del stock, alertas y reposiciones.','M21 16V8M3 8l9-5 9 5-9 5-9-5zM3 8v8l9 5 9-5M12 13v8'],
                ['inventario','Inventario','Registra productos y actualiza existencias.','M4 7h16M4 12h16M4 17h16M8 4v6M16 14v6'],
            ] as [$key,$title,$description,$path])
                <button class="manager-card" type="button" data-manager-card="{{ $key }}" data-manager-search="{{ Str::lower($title.' '.$description) }}">
                    <span class="manager-card-icon"><svg class="icon" viewBox="0 0 24 24"><path d="{{ $path }}"/></svg></span>
                    <span><b>{{ $title }}</b><span>{{ $description }}</span></span><span class="manager-arrow">›</span>
                </button>
            @endforeach
        </section>
        <div class="manager-empty" id="manager-empty">No encontramos un informe con ese nombre.</div>
        <div class="manager-detail-head"><button class="manager-back" type="button" aria-label="Volver"><svg class="icon" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></button><h2 id="manager-detail-title">Manager</h2></div>

        <section class="manager-panel" data-manager-panel="rendimiento" data-manager-title="Análisis de rendimiento">
            <div class="report-stack">
                <article class="card">
                    <div class="report-card-title"><div><h2>Rendimiento del período</h2><span>Indicadores operativos y económicos.</span></div><span>{{ $activeAppointments->count() }} citas válidas</span></div>
                    <div class="report-kpis">
                        <div class="report-kpi good"><span>Ingresos previstos</span><b>${{ number_format($expectedRevenueCents/100,2) }}</b><small>{{ $revenueChange >= 0 ? '+' : '' }}{{ $revenueChange }}% frente al mes anterior</small></div>
                        <div class="report-kpi"><span>Ocupación estimada</span><b>{{ $occupancyRate }}%</b><small>Tiempo reservado frente al disponible</small></div>
                        <div class="report-kpi"><span>Ticket medio</span><b>${{ number_format($averageTicketCents/100,2) }}</b><small>Promedio por cita no cancelada</small></div>
                        <div class="report-kpi {{ $cancellationRate > 15 ? 'warn' : '' }}"><span>Cancelaciones</span><b>{{ $cancellationRate }}%</b><small>{{ $cancelledAppointments->count() }} cita(s) cancelada(s)</small></div>
                    </div>
                </article>
                <div class="report-grid-2">
                    <article class="card"><div class="report-card-title"><div><h2>Evolución de ingresos</h2><span>Últimos seis meses.</span></div></div><div class="trend-chart">@foreach($monthlyTrend as $trend)<div class="trend-column"><span class="trend-value">${{ number_format($trend['revenue_cents']/100,0) }}</span><div class="trend-bar-wrap"><div class="trend-bar" style="height:{{ max(4,round(($trend['revenue_cents']/$maxTrendRevenue)*100)) }}%;"></div></div><span class="trend-label">{{ $trend['label'] }}</span></div>@endforeach</div></article>
                    <article class="card"><div class="report-card-title"><div><h2>Lectura rápida</h2><span>Lo más importante del período.</span></div></div><div class="insight-list">
                        <div class="insight"><span class="insight-icon">★</span><div><b>{{ $serviceReport->first()['service']->name ?? 'Sin servicio destacado' }}</b><span>Servicio con mayor ingreso previsto.</span></div></div>
                        <div class="insight"><span class="insight-icon">↗</span><div><b>{{ $busiestWeekday }}</b><span>Día de la semana con mayor actividad.</span></div></div>
                        <div class="insight"><span class="insight-icon">%</span><div><b>{{ $noShowAppointments->count() }} inasistencia(s)</b><span>Conviene revisar recordatorios si este valor aumenta.</span></div></div>
                    </div></article>
                </div>
            </div>
        </section>

        <section class="manager-panel" data-manager-panel="empresa" data-manager-title="Informe de empresa">
            <div class="report-stack">
                <article class="card"><div class="report-card-title"><div><h2>Salud de {{ $clinic->name }}</h2><span>Resumen general del negocio.</span></div><span>{{ ucfirst($clinic->subscription_status ?? 'pendiente') }}</span></div><div class="report-kpis">
                    <div class="report-kpi"><span>Clientes</span><b>{{ $allClients->count() }}</b><small>{{ $newClients }} nuevo(s) este mes</small></div>
                    <div class="report-kpi"><span>Equipo activo</span><b>{{ $professionalReport->count() }}</b><small>Profesionales visibles</small></div>
                    <div class="report-kpi"><span>Servicios</span><b>{{ $serviceReport->count() }}</b><small>{{ $serviceReport->where('service.price_cents', null)->count() }} sin precio</small></div>
                    <div class="report-kpi {{ $lowStockItems->count() ? 'warn' : 'good' }}"><span>Alertas de stock</span><b>{{ $lowStockItems->count() }}</b><small>{{ $outOfStockItems->count() }} producto(s) agotado(s)</small></div>
                </div></article>
                <div class="report-grid-2">
                    <article class="card"><div class="report-card-title"><div><h2>Actividad del mes</h2><span>Movimiento registrado.</span></div></div><div class="insight-list">
                        <div class="insight"><span class="insight-icon">C</span><div><b>{{ $appointments->count() }} citas creadas</b><span>{{ $activeAppointments->count() }} continúan activas.</span></div></div>
                        <div class="insight"><span class="insight-icon">✓</span><div><b>{{ $attendedAppointments->count() }} asistencias</b><span>Marcadas como atendidas o completadas.</span></div></div>
                        <div class="insight"><span class="insight-icon">$</span><div><b>${{ number_format($realizedRevenueCents/100,2) }} realizados</b><span>Basado en citas marcadas como atendidas.</span></div></div>
                    </div></article>
                    <article class="card"><div class="report-card-title"><div><h2>Prioridades recomendadas</h2><span>Acciones para mejorar la información.</span></div></div><div class="insight-list">
                        <div class="insight"><span class="insight-icon">1</span><div><b>Completar precios</b><span>{{ $serviceReport->filter(fn($row) => $row['service']->price_cents === null)->count() }} servicio(s) todavía no aportan valor a los informes.</span></div></div>
                        <div class="insight"><span class="insight-icon">2</span><div><b>Registrar asistencias</b><span>Marca atendidas e inasistencias para medir ingresos reales.</span></div></div>
                        <div class="insight"><span class="insight-icon">3</span><div><b>Cuidar clientes inactivos</b><span>{{ $inactiveClients }} cliente(s) llevan más de tres meses sin asistir.</span></div></div>
                    </div></article>
                </div>
            </div>
        </section>

        <section class="manager-panel" data-manager-panel="facturacion" data-manager-title="Facturación">
            <div class="report-stack">
                <article class="card"><div class="report-card-title"><div><h2>Informe económico</h2><span>Los importes son estimaciones basadas en los precios de servicios.</span></div><a class="btn" href="/facturacion">Facturación de la plataforma</a></div><div class="report-kpis">
                    <div class="report-kpi good"><span>Previsto</span><b>${{ number_format($expectedRevenueCents/100,2) }}</b><small>Citas no canceladas</small></div>
                    <div class="report-kpi"><span>Realizado</span><b>${{ number_format($realizedRevenueCents/100,2) }}</b><small>Citas atendidas</small></div>
                    <div class="report-kpi"><span>Ticket medio</span><b>${{ number_format($averageTicketCents/100,2) }}</b><small>Importe promedio</small></div>
                    <div class="report-kpi"><span>Depósitos</span><b>${{ number_format($activeAppointments->sum('deposit_cents')/100,2) }}</b><small>Importes registrados</small></div>
                </div></article>
                <article class="card"><div class="report-card-title"><div><h2>Ingresos por servicio</h2><span>Ordenados por impacto económico.</span></div></div><div class="table-scroll"><table class="report-table"><thead><tr><th>Servicio</th><th>Precio</th><th>Citas</th><th>Ingreso previsto</th></tr></thead><tbody>@forelse($serviceReport as $row)<tr><td><b>{{ $row['service']->name }}</b></td><td>{{ $row['service']->price_cents !== null ? '$'.number_format($row['service']->price_cents/100,2) : 'Sin precio' }}</td><td>{{ $row['appointments'] }}</td><td><b>${{ number_format($row['revenue_cents']/100,2) }}</b></td></tr>@empty<tr><td colspan="4">No hay servicios activos.</td></tr>@endforelse</tbody></table></div></article>
            </div>
        </section>

        <section class="manager-panel" data-manager-panel="profesionales" data-manager-title="Informe de profesionales">
            <article class="card"><div class="report-card-title"><div><h2>Rendimiento del equipo</h2><span>Comparativa basada en citas asignadas.</span></div></div><div class="table-scroll"><table class="report-table"><thead><tr><th>Profesional</th><th>Citas</th><th>Asistencias</th><th>Canceladas</th><th>Ticket medio</th><th>Ingreso previsto</th></tr></thead><tbody>@forelse($professionalReport as $row)<tr><td><div class="person-name"><span class="person-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row['stylist']->name,0,2)) }}</span>{{ $row['stylist']->name }}</div></td><td>{{ $row['appointments'] }}</td><td>{{ $row['attended'] }}</td><td>{{ $row['cancelled'] }}</td><td>${{ number_format($row['average_ticket_cents']/100,2) }}</td><td><b>${{ number_format($row['revenue_cents']/100,2) }}</b></td></tr>@empty<tr><td colspan="6">No hay profesionales activos.</td></tr>@endforelse</tbody></table></div></article>
        </section>

        <section class="manager-panel" data-manager-panel="clientes" data-manager-title="Informe de clientes">
            <div class="report-stack"><article class="card"><div class="report-card-title"><div><h2>Comunidad de clientes</h2><span>Fidelidad y relación con el centro.</span></div></div><div class="report-kpis">
                <div class="report-kpi"><span>Total</span><b>{{ $allClients->count() }}</b><small>Clientes registrados</small></div>
                <div class="report-kpi good"><span>Nuevos</span><b>{{ $newClients }}</b><small>Altas del período</small></div>
                <div class="report-kpi"><span>Recurrentes</span><b>{{ $recurringClients }}</b><small>Dos o más citas válidas</small></div>
                <div class="report-kpi"><span>Favoritos / VIP</span><b>{{ $allClients->where('loyalty_level','>',0)->count() }}</b><small>{{ $allClients->where('loyalty_level',2)->count() }} VIP</small></div>
            </div></article><article class="card"><div class="report-card-title"><div><h2>Clientes destacados</h2><span>Ordenados por ingresos realizados y visitas.</span></div><a class="btn" href="/clientes">Ver clientes</a></div><div class="table-scroll"><table class="report-table"><thead><tr><th>Cliente</th><th>Categoría</th><th>Citas</th><th>Última visita</th><th>Ingresos</th></tr></thead><tbody>@forelse($topClients as $row)<tr><td><b>{{ trim($row['client']->first_name.' '.$row['client']->last_name) }}</b></td><td>{{ $row['client']->loyalty_level === 2 ? 'VIP' : ($row['client']->loyalty_level === 1 ? 'Favorito' : 'Regular') }}</td><td>{{ $row['appointments'] }}</td><td>{{ $row['last_visit']?->timezone($timezone)->format('d/m/Y') ?? 'Sin visita' }}</td><td><b>${{ number_format($row['revenue_cents']/100,2) }}</b></td></tr>@empty<tr><td colspan="5">No hay clientes registrados.</td></tr>@endforelse</tbody></table></div></article></div>
        </section>

        <section class="manager-panel" data-manager-panel="almacen" data-manager-title="Informe de almacén">
            <div class="report-stack"><article class="card"><div class="report-card-title"><div><h2>Estado del almacén</h2><span>Valor y disponibilidad de productos activos.</span></div></div><div class="report-kpis">
                <div class="report-kpi"><span>Productos</span><b>{{ $inventoryItems->count() }}</b><small>Referencias activas</small></div>
                <div class="report-kpi"><span>Valor a coste</span><b>${{ number_format($inventoryCostCents/100,2) }}</b><small>Capital estimado en existencias</small></div>
                <div class="report-kpi good"><span>Valor de venta</span><b>${{ number_format($inventoryRetailCents/100,2) }}</b><small>Potencial bruto registrado</small></div>
                <div class="report-kpi {{ $lowStockItems->count() ? 'warn' : 'good' }}"><span>Reponer</span><b>{{ $lowStockItems->count() }}</b><small>{{ $outOfStockItems->count() }} agotado(s)</small></div>
            </div></article><article class="card"><div class="report-card-title"><div><h2>Alertas de reposición</h2><span>Productos en o por debajo de su mínimo.</span></div><button class="btn" type="button" data-open-manager="inventario">Gestionar inventario</button></div>@forelse($lowStockItems as $item)<div class="insight" style="margin-top:10px;"><span class="insight-icon">!</span><div><b>{{ $item->name }}</b><span>{{ number_format((float)$item->current_stock,2) }} {{ $item->unit }} disponibles · mínimo {{ number_format((float)$item->minimum_stock,2) }}</span></div></div>@empty<div class="empty-report">No hay alertas de reposición. El almacén está bajo control.</div>@endforelse</article></div>
        </section>

        <section class="manager-panel" data-manager-panel="inventario" data-manager-title="Inventario">
            <div class="report-stack"><article class="card"><div class="report-card-title"><div><h2>Agregar producto</h2><span>Crea una referencia y define cuándo debe avisarte Manager.</span></div></div><form class="inventory-form" method="POST" action="{{ route('manager.inventory.store') }}">@csrf
                <div class="span-2"><label>Producto</label><input name="name" value="{{ old('name') }}" required placeholder="Ej. Champú hidratante"></div><div><label>SKU / referencia</label><input name="sku" value="{{ old('sku') }}" placeholder="CH-001"></div><div><label>Categoría</label><input name="category" value="{{ old('category') }}" placeholder="Cabello"></div>
                <div><label>Existencias actuales</label><input name="current_stock" type="number" min="0" step="0.01" value="{{ old('current_stock',0) }}" required></div><div><label>Stock mínimo</label><input name="minimum_stock" type="number" min="0" step="0.01" value="{{ old('minimum_stock',2) }}" required></div><div><label>Unidad</label><select name="unit"><option value="unidad">Unidad</option><option value="ml">Mililitros</option><option value="g">Gramos</option><option value="caja">Caja</option><option value="paquete">Paquete</option></select></div><div><label>Coste</label><input name="cost" type="number" min="0" step="0.01" placeholder="0.00"></div><div><label>Precio de venta</label><input name="sale_price" type="number" min="0" step="0.01" placeholder="0.00"></div><div style="display:flex;align-items:end;"><button class="btn primary" type="submit">Agregar al inventario</button></div>
            </form></article><article class="card"><div class="report-card-title"><div><h2>Existencias</h2><span>Ajusta entradas con números positivos y consumos con negativos.</span></div></div><div class="table-scroll"><table class="report-table"><thead><tr><th>Producto</th><th>Categoría</th><th>Existencias</th><th>Mínimo</th><th>Estado</th><th>Ajustar</th></tr></thead><tbody>@forelse($inventoryItems as $item)<tr><td><b>{{ $item->name }}</b><br><small>{{ $item->sku ?: 'Sin SKU' }}</small></td><td>{{ $item->category ?: 'General' }}</td><td>{{ number_format((float)$item->current_stock,2) }} {{ $item->unit }}</td><td>{{ number_format((float)$item->minimum_stock,2) }}</td><td><span class="stock-status {{ (float)$item->current_stock <= 0 ? 'out' : ($item->isLowStock() ? 'low' : '') }}">{{ (float)$item->current_stock <= 0 ? 'Agotado' : ($item->isLowStock() ? 'Reponer' : 'Correcto') }}</span></td><td><form class="stock-adjust" method="POST" action="{{ route('manager.inventory.adjust',$item) }}">@csrf @method('PATCH')<input name="adjustment" type="number" step="0.01" placeholder="+ / -" required><button class="btn" type="submit">Aplicar</button></form></td></tr>@empty<tr><td colspan="6"><div class="empty-report">Todavía no hay productos. Agrega el primero arriba.</div></td></tr>@endforelse</tbody></table></div></article></div>
        </section>
    </div>

    <script>
        const managerCards = Array.from(document.querySelectorAll('[data-manager-card]'));
        const managerPanels = Array.from(document.querySelectorAll('[data-manager-panel]'));
        const managerShell = document.querySelector('.manager-shell');
        const managerTitle = document.getElementById('manager-detail-title');
        const managerSearch = document.getElementById('manager-search');
        const managerEmpty = document.getElementById('manager-empty');

        function showManagerPanel(panelName) {
            managerCards.forEach((card) => card.classList.toggle('active', card.dataset.managerCard === panelName));
            managerPanels.forEach((panel) => {
                const active = panel.dataset.managerPanel === panelName;
                panel.classList.toggle('active', active);
                if (active && managerTitle) managerTitle.textContent = panel.dataset.managerTitle || 'Manager';
            });
            managerShell?.classList.add('is-detail');
            history.replaceState(null, '', `#${panelName}`);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        managerCards.forEach((card) => card.addEventListener('click', () => showManagerPanel(card.dataset.managerCard)));
        document.querySelector('.manager-back')?.addEventListener('click', () => {
            managerShell?.classList.remove('is-detail');
            managerCards.forEach((card) => card.classList.remove('active'));
            history.replaceState(null, '', window.location.pathname + window.location.search);
        });
        document.querySelectorAll('[data-open-manager]').forEach((button) => button.addEventListener('click', () => showManagerPanel(button.dataset.openManager)));
        managerSearch?.addEventListener('input', () => {
            const query = managerSearch.value.trim().toLowerCase();
            let visible = 0;
            managerCards.forEach((card) => {
                const matches = !query || `${card.textContent} ${card.dataset.managerSearch || ''}`.toLowerCase().includes(query);
                card.style.display = matches ? '' : 'none';
                visible += matches ? 1 : 0;
            });
            managerEmpty?.classList.toggle('active', visible === 0);
        });
        const initialManagerPanel = window.location.hash.replace('#','') || @json($errors->any() ? 'inventario' : '');
        if (managerPanels.some((panel) => panel.dataset.managerPanel === initialManagerPanel)) showManagerPanel(initialManagerPanel);
    </script>
@endsection
