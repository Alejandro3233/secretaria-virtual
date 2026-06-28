@extends('layouts.app')

@section('title', 'Campañas - Secretary365')
@section('page_title', 'Campañas')
@section('page_subtitle', 'Crea ofertas flash y mide su impacto en reservas e ingresos.')
@section('page_actions')
    <a class="btn primary" href="{{ route('campaigns.create') }}">Crear oferta flash</a>
@endsection

@section('content')
<style>
    .campaign-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
    .campaign-summary .card { padding:16px; }
    .campaign-summary span { display:block; color:var(--muted); font-size:12px; font-weight:800; }
    .campaign-summary b { display:block; margin-top:6px; font-size:25px; }
    .campaign-list { display:grid; gap:12px; }
    .campaign-row { display:grid; grid-template-columns:minmax(220px,1.5fr) repeat(5,minmax(90px,.6fr)) auto; gap:14px; align-items:center; }
    .campaign-row h2 { margin:0 0 5px; font-size:17px; }
    .campaign-row p { margin:0; color:var(--muted); font-size:12px; }
    .campaign-metric span { display:block; color:var(--muted); font-size:11px; font-weight:800; }
    .campaign-metric b { display:block; margin-top:4px; font-size:16px; }
    .campaign-state { display:inline-flex; align-items:center; gap:6px; padding:5px 8px; border-radius:999px; background:#f3f4f6; font-size:11px; font-weight:900; }
    .campaign-state::before { content:""; width:7px; height:7px; border-radius:50%; background:#6b7280; }
    .campaign-state.active { color:#166534; background:#dcfce7; }.campaign-state.active::before{background:#16a34a}
    .campaign-state.draft { color:#92400e; background:#fef3c7; }.campaign-state.draft::before{background:#d97706}
    @media(max-width:1100px){.campaign-row{grid-template-columns:1fr 1fr 1fr}.campaign-row-main{grid-column:1/-1}.campaign-summary{grid-template-columns:1fr 1fr}}
</style>
@php
    $activeCount = $campaigns->filter->isActive()->count();
    $totalBookings = $campaigns->sum(fn($campaign)=>(int)$campaign->metrics['bookings']);
    $totalRevenue = $campaigns->sum(fn($campaign)=>(int)$campaign->metrics['revenue_cents']);
    $totalRecipients = $campaigns->sum(fn($campaign)=>(int)$campaign->metrics['recipients']);
@endphp
<section class="campaign-summary">
    <article class="card"><span>Ofertas activas</span><b>{{ $activeCount }}</b></article>
    <article class="card"><span>Destinatarios</span><b>{{ $totalRecipients }}</b></article>
    <article class="card"><span>Reservas obtenidas</span><b>{{ $totalBookings }}</b></article>
    <article class="card"><span>Ingresos atribuidos</span><b>{{ number_format($totalRevenue/100,2) }} €</b></article>
</section>

@if(session('campaign_status'))<div class="card" style="margin-bottom:14px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">{{ session('campaign_status') }}</div>@endif

<section class="campaign-list">
@forelse($campaigns as $campaign)
    @php
        $state = $campaign->status === 'draft' ? 'draft' : ($campaign->isActive() ? 'active' : 'ended');
        $stateLabel = $state === 'draft' ? 'Borrador' : ($state === 'active' ? 'Activa' : 'Finalizada');
    @endphp
    <article class="card campaign-row">
        <div class="campaign-row-main">
            <span class="campaign-state {{ $state }}">{{ $stateLabel }}</span>
            <h2>{{ $campaign->name }}</h2>
            <p>{{ $campaign->discount_percent }}% en {{ $campaign->service?->name ?? 'Servicio eliminado' }} · vence {{ $campaign->expires_at->timezone($clinic->localTimezone())->format('d/m/Y H:i') }}</p>
        </div>
        <div class="campaign-metric"><span>Destinatarios</span><b>{{ $campaign->metrics['recipients'] }}</b></div>
        <div class="campaign-metric"><span>Correo</span><b>{{ $campaign->metrics['email_sent'] }}</b></div>
        <div class="campaign-metric"><span>SMS</span><b>{{ $campaign->metrics['sms_sent'] }}</b></div>
        <div class="campaign-metric"><span>Reservas</span><b>{{ $campaign->metrics['bookings'] }}</b></div>
        <div class="campaign-metric"><span>Ingresos</span><b>{{ number_format($campaign->metrics['revenue_cents']/100,2) }} €</b></div>
        <a class="btn" href="{{ route('campaigns.show',$campaign) }}">Ver campaña</a>
    </article>
@empty
    <article class="card" style="padding:34px;text-align:center;"><h2 style="margin-top:0;">Todavia no hay campañas</h2><p class="subtitle">Crea tu primera oferta flash y elige a quienes quieres enviarla.</p><a class="btn primary" href="{{ route('campaigns.create') }}">Crear oferta flash</a></article>
@endforelse
</section>
@endsection
