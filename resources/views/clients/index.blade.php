@extends('layouts.app')

@section('title', 'Clientes - Secretary365')
@section('page_title', 'Clientes')
@section('page_subtitle', 'Directorio, historial de visitas y relación con cada cliente.')
@section('page_actions')
    <a class="btn primary" href="{{ route('clients.create') }}">Nuevo cliente</a>
@endsection

@section('content')
<style>
    .client-shell { display:grid; grid-template-columns:320px minmax(0,1fr); gap:16px; min-height:680px; }
    .client-directory, .client-profile { background:#fff; border:1px solid var(--line); border-radius:10px; }
    .client-directory { padding:16px 0; overflow:hidden; }
    .client-directory-head { padding:0 16px 14px; }
    .client-directory-head h2 { font-size:24px; }
    .client-search { position:relative; margin-top:14px; }
    .client-search input { padding-left:38px; min-height:42px; }
    .client-search svg { position:absolute; left:12px; top:12px; width:18px; height:18px; fill:none; stroke:#70646b; stroke-width:2; }
    .client-filters { display:grid; grid-template-columns:repeat(3,1fr); gap:5px; margin-top:10px; padding:4px; border-radius:8px; background:#f5f1f3; }
    .client-filters a { padding:7px 5px; border-radius:6px; color:var(--muted); font-size:11px; font-weight:900; text-align:center; }
    .client-filters a.active { background:#fff; color:var(--brand); box-shadow:0 1px 4px rgba(45,25,35,.1); }
    .client-count { margin:12px 16px 6px; color:var(--muted); font-size:12px; font-weight:900; text-transform:uppercase; }
    .client-list { max-height:600px; overflow:auto; }
    .client-row { display:grid; grid-template-columns:42px minmax(0,1fr); gap:11px; align-items:center; padding:12px 16px; border-top:1px solid #f2eaf0; }
    .client-row:hover, .client-row.active { background:#fff4f7; }
    .client-avatar { width:42px; height:42px; display:grid; place-items:center; overflow:visible; border-radius:50%; background:#f1e8ed; color:var(--brand-dark); font-weight:900; line-height:1; text-align:center; }
    .client-row > span:last-child b, .client-row > span:last-child span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .client-row > span:last-child span { margin-top:3px; color:var(--muted); font-size:12px; }
    .client-empty { padding:38px 18px; color:var(--muted); text-align:center; }
    .client-profile { padding:22px; }
    .profile-head { position:relative; display:grid; justify-items:center; text-align:center; padding:10px 50px 22px; }
    .profile-avatar { width:82px; height:82px; display:grid; place-items:center; border-radius:50%; background:#f1e8ed; color:#8b7b84; font-size:28px; font-weight:800; }
    .profile-head h2 { margin-top:12px; font-size:27px; }
    .profile-contact { margin-top:5px; color:var(--muted); }
    .loyalty-picker { display:flex; align-items:center; justify-content:center; gap:5px; margin-top:12px; }
    .loyalty-picker form { margin:0; }
    .loyalty-star { width:38px; height:38px; border:1px solid #e4d7a2; border-radius:50%; background:#fffdf4; color:#c9b86d; font-size:22px; line-height:1; cursor:pointer; transition:.15s ease; }
    .loyalty-star:hover { transform:translateY(-1px); border-color:#d6ad20; color:#d6ad20; }
    .loyalty-star.active { border-color:#e0ad13; background:#fff4c7; color:#e0a800; box-shadow:0 0 0 3px rgba(224,168,0,.12); }
    .loyalty-label { margin-left:7px; border-radius:999px; padding:6px 10px; background:#f5f1f3; color:var(--muted); font-size:11px; font-weight:900; text-transform:uppercase; }
    .loyalty-label.favorite { background:#fff8d9; color:#8a6500; }
    .loyalty-label.vip { background:#2a1922; color:#ffd75e; }
    .client-loyalty-mini { display:inline-flex !important; width:max-content; margin-top:5px !important; border-radius:999px; padding:3px 7px; background:#fff8d9; color:#8a6500 !important; font-size:10px !important; font-weight:900; }
    .client-loyalty-mini.vip { background:#2a1922; color:#ffd75e !important; }
    .profile-edit { position:absolute; top:0; left:0; }
    .profile-new-appointment { position:absolute; top:0; right:0; }
    .profile-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; margin-top:12px; border:1px solid var(--line); border-radius:9px; overflow:hidden; background:var(--line); }
    .profile-metric { min-height:92px; padding:15px; background:white; }
    .profile-metric span, .profile-metric b { display:block; }
    .profile-metric span { color:var(--muted); font-size:10px; font-weight:900; text-transform:uppercase; }
    .profile-metric b { margin-top:8px; font-size:19px; }
    .client-details { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:16px; }
    .client-detail-card, .history-card { border:1px solid var(--line); border-radius:9px; padding:16px; }
    .client-detail-card h3, .history-card h3 { margin:0 0 12px; font-size:16px; }
    .client-note-form textarea { width:100%; min-height:126px; border:1px solid #dbcbd4; border-radius:6px; padding:11px; resize:vertical; line-height:1.45; }
    .client-note-form .actions { justify-content:flex-end; margin-top:10px; }
    .detail-line { display:grid; grid-template-columns:105px 1fr; gap:10px; padding:7px 0; border-bottom:1px solid #f4edf1; }
    .detail-line:last-child { border:0; }
    .detail-line span { color:var(--muted); font-size:12px; font-weight:800; }
    .history-card { margin-top:16px; }
    .history-tabs { display:flex; gap:8px; margin-bottom:14px; }
    .history-tabs a { padding:7px 12px; border-radius:999px; background:#f4edf1; color:var(--muted); font-size:12px; font-weight:900; }
    .history-tabs a.active { background:var(--brand); color:white; }
    .visit-row { display:grid; grid-template-columns:125px minmax(0,1fr) auto; gap:14px; align-items:center; padding:13px 0; border-top:1px solid #f2eaf0; }
    .visit-date b, .visit-date span, .visit-main b, .visit-main span { display:block; }
    .visit-date span, .visit-main span { margin-top:3px; color:var(--muted); font-size:12px; }
    .cancellation-notice { display:inline-flex; margin-top:7px; border-radius:5px; padding:5px 8px; background:#fff1f2; color:#9f1239; font-size:11px; font-weight:900; }
    .cancellation-notice.unverified { background:#f1f5f9; color:#475569; }
    .visit-actions { display:flex; align-items:center; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
    .visit-actions form { margin:0; }
    .visit-actions .btn { min-height:30px; padding:0 9px; font-size:11px; }
    @media(max-width:980px){ .client-shell{grid-template-columns:1fr}.client-list{max-height:300px}.profile-metrics{grid-template-columns:repeat(2,1fr)} }
    @media(max-width:620px){ .client-profile{padding:14px}.client-details{grid-template-columns:1fr}.visit-row{grid-template-columns:1fr}.visit-actions{justify-content:flex-start}.profile-head{padding-inline:0;padding-top:55px}.profile-metrics{grid-template-columns:1fr 1fr} }
</style>

@if(session('client_status'))<div class="card" style="margin-bottom:14px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">{{ session('client_status') }}</div>@endif

<div class="client-shell">
    <aside class="client-directory">
        <div class="client-directory-head">
            <h2>Clientes</h2>
            <form class="client-search" method="GET" action="{{ route('clients.index') }}">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input name="q" value="{{ $search }}" placeholder="Buscar cliente..." autocomplete="off">
                @if($loyaltyFilter !== 'todos')<input type="hidden" name="categoria" value="{{ $loyaltyFilter }}">@endif
            </form>
            <nav class="client-filters" aria-label="Filtrar clientes por categoría">
                <a class="{{ $loyaltyFilter === 'todos' ? 'active' : '' }}" href="{{ $selectedClient ? route('clients.show', ['client' => $selectedClient, 'q' => $search ?: null]) : route('clients.index', ['q' => $search ?: null]) }}">Todos</a>
                <a class="{{ $loyaltyFilter === 'favoritos' ? 'active' : '' }}" href="{{ $selectedClient ? route('clients.show', ['client' => $selectedClient, 'categoria' => 'favoritos', 'q' => $search ?: null]) : route('clients.index', ['categoria' => 'favoritos', 'q' => $search ?: null]) }}">★ Favoritos</a>
                <a class="{{ $loyaltyFilter === 'vip' ? 'active' : '' }}" href="{{ $selectedClient ? route('clients.show', ['client' => $selectedClient, 'categoria' => 'vip', 'q' => $search ?: null]) : route('clients.index', ['categoria' => 'vip', 'q' => $search ?: null]) }}">★★ VIP</a>
            </nav>
        </div>
        <div class="client-count">Lista ({{ $clients->count() }})</div>
        <div class="client-list">
            @forelse($clients as $client)
                <a class="client-row {{ $selectedClient?->id === $client->id ? 'active' : '' }}" href="{{ route('clients.show', ['client'=>$client, 'q'=>$search ?: null, 'categoria'=>$loyaltyFilter !== 'todos' ? $loyaltyFilter : null]) }}">
                    <span class="client-avatar">{{ $client->initials() }}</span>
                    <span><b>{{ trim($client->first_name.' '.$client->last_name) }}</b><span>{{ $client->phone }}</span>@if($client->loyalty_level)<span class="client-loyalty-mini {{ $client->loyalty_level === 2 ? 'vip' : '' }}">{{ $client->loyalty_level === 2 ? '★★ VIP' : '★ Favorito' }}</span>@endif</span>
                </a>
            @empty
                <div class="client-empty">No encontramos clientes.<br><a class="btn primary" style="margin-top:14px;" href="{{ route('clients.create') }}">Crear el primero</a></div>
            @endforelse
        </div>
    </aside>

    <main class="client-profile">
        @if($selectedClient)
            @php
                $fullName = trim($selectedClient->first_name.' '.$selectedClient->last_name);
            @endphp
            <header class="profile-head">
                <a class="btn profile-edit" href="{{ route('clients.edit',$selectedClient) }}">Editar</a>
                <a class="btn primary profile-new-appointment" href="/agenda/nueva-cita?client_id={{ $selectedClient->id }}">Nueva cita</a>
                <div class="profile-avatar">{{ $selectedClient->initials() }}</div>
                <h2>{{ $fullName }}</h2>
                <div class="profile-contact">{{ $selectedClient->phone }} @if($selectedClient->email) · {{ $selectedClient->email }} @endif</div>
                <div class="loyalty-picker" aria-label="Clasificación del cliente">
                    <form method="POST" action="{{ route('clients.loyalty', $selectedClient) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="loyalty_level" value="{{ $selectedClient->loyalty_level === 1 ? 0 : 1 }}">
                        <button class="loyalty-star {{ $selectedClient->loyalty_level >= 1 ? 'active' : '' }}" type="submit" title="Marcar como Favorito" aria-label="Marcar como Favorito">★</button>
                    </form>
                    <form method="POST" action="{{ route('clients.loyalty', $selectedClient) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="loyalty_level" value="{{ $selectedClient->loyalty_level === 2 ? 0 : 2 }}">
                        <button class="loyalty-star {{ $selectedClient->loyalty_level === 2 ? 'active' : '' }}" type="submit" title="Marcar como VIP" aria-label="Marcar como VIP">★</button>
                    </form>
                    <span class="loyalty-label {{ $selectedClient->loyalty_level === 2 ? 'vip' : ($selectedClient->loyalty_level === 1 ? 'favorite' : '') }}">{{ $selectedClient->loyalty_level === 2 ? 'Cliente VIP' : ($selectedClient->loyalty_level === 1 ? 'Favorito' : 'Sin categoría') }}</span>
                </div>
            </header>

            <section class="profile-metrics">
                <div class="profile-metric"><span>Citas</span><b>{{ $stats['appointments'] }}</b></div>
                <div class="profile-metric"><span>Asistencias</span><b>{{ $stats['attended'] }}</b></div>
                <div class="profile-metric"><span>Inasistencias</span><b>{{ $stats['no_shows'] }}</b></div>
                <div class="profile-metric"><span>Cancelaciones</span><b>{{ $stats['cancelled'] }}</b></div>
                <div class="profile-metric"><span>Última visita</span><b>{{ $stats['last_visit']?->timezone($clinic->localTimezone())->format('d/m/Y') ?? '—' }}</b></div>
                <div class="profile-metric"><span>Ingresos registrados</span><b>${{ number_format($stats['revenue_cents']/100,2) }}</b></div>
                <div class="profile-metric"><span>Cliente desde</span><b>{{ $selectedClient->created_at?->format('d/m/Y') }}</b></div>
                <div class="profile-metric"><span>Riesgo de cancelación</span><b><span class="status {{ $stats['cancellation_risk_class'] }}">{{ $stats['cancellation_risk'] }}</span></b><small style="display:block;margin-top:6px;color:var(--muted);">{{ $stats['client_cancellations_measured'] }} cancelación(es) del cliente medidas</small></div>
            </section>

            <section class="client-details">
                <article class="client-detail-card"><h3>Información del cliente</h3>
                    <div class="detail-line"><span>Teléfono</span><b>{{ $selectedClient->phone }}</b></div>
                    <div class="detail-line"><span>Email</span><b>{{ $selectedClient->email ?: 'Sin registrar' }}</b></div>
                    <div class="detail-line"><span>Dirección</span><b>{{ $selectedClient->address ?: 'Sin registrar' }}</b></div>
                </article>
                <article class="client-detail-card">
                    <h3>Notas del cliente</h3>
                    <form class="client-note-form" method="POST" action="{{ route('clients.notes', $selectedClient) }}">
                        @csrf
                        @method('PUT')
                        <label for="client_notes">Notas internas</label>
                        <textarea id="client_notes" name="notes" maxlength="3000" placeholder="Ejemplo: Es alérgica a un medicamento, prefiere productos sin fragancia, no llamar antes de las 10:00.">{{ old('notes', $selectedClient->notes) }}</textarea>
                        @error('notes')<div class="danger" style="margin-top:8px;">{{ $message }}</div>@enderror
                        <div class="actions"><button class="btn primary" type="submit">Guardar notas</button></div>
                    </form>
                </article>
            </section>

            <section class="history-card">
                @php
                    $historyView = request('historial', 'pasadas');
                @endphp
                <h3>Historial de asistencias al centro</h3>
                <div class="history-tabs">
                    <a class="{{ $historyView==='pasadas'?'active':'' }}" href="{{ route('clients.show',['client'=>$selectedClient,'historial'=>'pasadas','categoria'=>$loyaltyFilter !== 'todos' ? $loyaltyFilter : null,'q'=>$search ?: null]) }}">Pasadas ({{ $pastAppointments->count() }})</a>
                    <a class="{{ $historyView==='proximas'?'active':'' }}" href="{{ route('clients.show',['client'=>$selectedClient,'historial'=>'proximas','categoria'=>$loyaltyFilter !== 'todos' ? $loyaltyFilter : null,'q'=>$search ?: null]) }}">Próximas ({{ $upcomingAppointments->count() }})</a>
                </div>
                @php
                    $visibleAppointments = $historyView === 'proximas' ? $upcomingAppointments : $pastAppointments;
                @endphp
                @forelse($visibleAppointments as $appointment)
                    @php
                        $statusLabel = match ($appointment->status) {
                            'attended', 'completed' => 'Asistió',
                            'no_show' => 'No asistió',
                            'cancelled', 'canceled' => 'Cancelada',
                            'confirmed' => 'Confirmada',
                            'pending' => 'Pendiente',
                            default => ucfirst($appointment->status),
                        };
                        $statusClass = match ($appointment->status) {
                            'attended', 'completed' => 'ok',
                            'no_show' => 'danger',
                            'cancelled', 'canceled' => 'cancelled-status',
                            default => 'wait',
                        };
                    @endphp
                    <div class="visit-row">
                        <div class="visit-date"><b>{{ $appointment->starts_at->timezone($clinic->localTimezone())->format('d/m/Y') }}</b><span>{{ $appointment->starts_at->timezone($clinic->localTimezone())->format('H:i') }}</span></div>
                        <div class="visit-main"><b>{{ $appointment->service?->name ?? $appointment->reason ?? 'Cita en el centro' }}</b><span>{{ $appointment->stylist?->name ? 'Con '.$appointment->stylist->name : 'Sin profesional asignado' }} @if($appointment->service?->price_cents) · ${{ number_format($appointment->service->price_cents/100,2) }} @endif</span>
                            @if(in_array($appointment->status,['cancelled','canceled'],true) && $appointment->cancellation_at)
                                <small class="cancellation-notice {{ $appointment->cancellation_by_client ? '' : 'unverified' }}">
                                    {{ $appointment->cancellation_by_client ? 'El cliente canceló' : 'Cancelación registrada' }} {{ $appointment->cancellation_notice_label }}
                                </small>
                            @endif
                        </div>
                        <div class="visit-actions"><span class="status {{ $statusClass }}">{{ $statusLabel }}</span>
                            @if($historyView==='pasadas' && !in_array($appointment->status,['cancelled','canceled'],true))
                                <form method="POST" action="{{ route('clients.attendance',[$selectedClient,$appointment]) }}">@csrf @method('PUT')<input type="hidden" name="attendance" value="attended"><button class="btn" type="submit">Asistió</button></form>
                                <form method="POST" action="{{ route('clients.attendance',[$selectedClient,$appointment]) }}">@csrf @method('PUT')<input type="hidden" name="attendance" value="no_show"><button class="btn" type="submit">No asistió</button></form>
                            @endif
                        </div>
                    </div>
                @empty<div class="client-empty">No hay citas {{ $historyView==='proximas'?'próximas':'pasadas' }} registradas.</div>@endforelse
            </section>
        @else
            <div class="client-empty" style="padding-top:140px;"><h2>Aún no hay clientes</h2><p>Crea el primer cliente para empezar su historial.</p><a class="btn primary" href="{{ route('clients.create') }}">Nuevo cliente</a></div>
        @endif
    </main>
</div>
@endsection
