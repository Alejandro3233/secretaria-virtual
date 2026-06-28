@extends('layouts.app')

@section('title', $campaign->name.' - Secretary365')
@section('page_title', $campaign->name)
@section('page_subtitle', $campaign->discount_percent.'% de descuento en '.($campaign->service?->name ?? 'servicio eliminado'))
@section('page_actions')
    <a class="btn" href="{{ route('campaigns.index') }}">Todas las campañas</a>
    @if($campaign->status==='active')<form method="POST" action="{{ route('campaigns.end',$campaign) }}" onsubmit="return confirm('¿Finalizar esta oferta ahora?');">@csrf<button class="btn danger" type="submit">Finalizar oferta</button></form>@endif
@endsection

@section('content')
<style>
    .campaign-detail-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(310px,.8fr);gap:18px;align-items:start}.campaign-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.campaign-kpi{padding:14px;border:1px solid var(--line);border-radius:8px;background:var(--soft)}.campaign-kpi span{display:block;color:var(--muted);font-size:11px;font-weight:800}.campaign-kpi b{display:block;margin-top:5px;font-size:21px}.message-preview{padding:18px;border:1px solid var(--line);border-radius:10px;background:#fff}.channel-pill{display:inline-flex;padding:5px 8px;border-radius:999px;background:#f3e8ee;color:var(--brand);font-size:11px;font-weight:900;margin-right:5px}.recipient-table{width:100%;border-collapse:collapse}.recipient-table th,.recipient-table td{text-align:left;padding:10px;border-bottom:1px solid var(--line);font-size:12px}.status-ok{color:#166534;font-weight:800}.status-bad{color:#991b1b;font-weight:800}@media(max-width:950px){.campaign-detail-grid{grid-template-columns:1fr}.campaign-kpis{grid-template-columns:1fr 1fr}}
</style>
@if(session('campaign_status'))<div class="card" style="margin-bottom:14px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">{{ session('campaign_status') }}</div>@endif
<div class="campaign-detail-grid">
<div style="display:grid;gap:14px;">
    <section class="card">
        <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;">
            <div><span class="channel-pill">{{ strtoupper(implode(' + ',$campaign->channels)) }}</span><h2 style="margin:9px 0 4px;">{{ $campaign->service?->name }}</h2><p class="subtitle" style="margin:0;">Vence {{ $campaign->expires_at->timezone($clinic->localTimezone())->format('d/m/Y H:i') }} · segmento {{ ['all'=>'Todos','inactive'=>'Inactivos','frequent'=>'Habituales','vip'=>'VIP'][$campaign->segment] }}</p></div>
            <div style="text-align:right;"><span style="font-size:30px;color:var(--brand);font-weight:900;">-{{ $campaign->discount_percent }}%</span><br>@if($campaign->discounted_price_cents!==null)<span style="text-decoration:line-through;color:var(--muted);">{{ number_format($campaign->original_price_cents/100,2) }} €</span> <b>{{ number_format($campaign->discounted_price_cents/100,2) }} €</b>@endif</div>
        </div>
    </section>
    <section class="campaign-kpis">
        <div class="campaign-kpi"><span>Destinatarios</span><b>{{ $campaign->metrics['recipients'] }}</b></div>
        <div class="campaign-kpi"><span>Envíos aceptados</span><b>{{ $campaign->metrics['email_sent']+$campaign->metrics['sms_sent'] }}</b></div>
        <div class="campaign-kpi"><span>Reservas obtenidas</span><b>{{ $campaign->metrics['bookings'] }}</b></div>
        <div class="campaign-kpi"><span>Ingresos atribuidos</span><b>{{ number_format($campaign->metrics['revenue_cents']/100,2) }} €</b></div>
    </section>
    <section class="card">
        <h2 style="margin-top:0;">Destinatarios y entregas</h2>
        <div style="overflow:auto;"><table class="recipient-table"><thead><tr><th>Cliente</th><th>Correo</th><th>SMS</th><th>Reserva</th></tr></thead><tbody>
        @forelse($campaign->recipients as $recipient)<tr><td>{{ trim(($recipient->client?->first_name ?? '').' '.($recipient->client?->last_name ?? '')) ?: 'Cliente eliminado' }}</td><td class="{{ in_array($recipient->email_status,['sent','delivered'])?'status-ok':($recipient->email_status==='failed'?'status-bad':'') }}">{{ $recipient->email_status ?? '—' }}</td><td class="{{ in_array($recipient->sms_status,['sent','delivered'])?'status-ok':(in_array($recipient->sms_status,['failed','undelivered'])?'status-bad':'') }}">{{ $recipient->sms_status ?? '—' }}</td><td>{{ $recipient->appointments->whereNotIn('status',['cancelled','canceled'])->count() ? 'Sí' : '—' }}</td></tr>@empty<tr><td colspan="4">No hay clientes con consentimiento para los canales y segmento elegidos.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
<aside class="card">
    <small style="color:var(--brand);font-weight:900;">PREVISUALIZACIÓN</small>
    <div class="message-preview" style="margin-top:10px;"><b>{{ $campaign->subject }}</b><p style="line-height:1.55;">{{ $campaign->message }}</p><div style="padding:13px;background:var(--soft);border-radius:8px;"><b>{{ $campaign->discount_percent }}% en {{ $campaign->service?->name }}</b><br><small>Hasta {{ $campaign->expires_at->timezone($clinic->localTimezone())->format('d/m/Y H:i') }}</small></div><span class="btn primary" style="margin-top:12px;pointer-events:none;">Reservar oferta</span></div>
    @if($previewRecipient && in_array('sms',$campaign->channels,true))<div style="margin-top:14px;"><b>Vista previa SMS</b><p class="subtitle" style="line-height:1.45;">{{ $service->smsBody($campaign,$previewRecipient) }}</p></div>@endif
    @if($campaign->status==='draft')
        <form method="POST" action="{{ route('campaigns.send',$campaign) }}" style="margin-top:16px;" onsubmit="return confirm('Se enviará esta oferta a {{ $campaign->recipients->count() }} cliente(s). ¿Continuar?');">@csrf<button class="btn primary" type="submit" style="width:100%;" @disabled($campaign->recipients->isEmpty())>Confirmar y enviar ahora</button></form>
        <p class="subtitle" style="font-size:11px;">El envío no comenzará hasta pulsar este botón.</p>
    @endif
    @if($campaign->status!=='draft')<div style="margin-top:14px;padding:12px;border-radius:8px;background:#f0fdf4;color:#166534;font-weight:800;">Campaña {{ $campaign->isActive()?'activa':'finalizada' }}.</div>@endif
</aside>
</div>
@endsection
