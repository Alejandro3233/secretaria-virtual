@extends('layouts.app')

@section('title', 'Nueva oferta flash - Secretary365')
@section('page_title', 'Nueva oferta flash')
@section('page_subtitle', 'Configura la promoción, el público y los canales. Podrás revisarla antes de enviar.')
@section('page_actions')<a class="btn" href="{{ route('campaigns.index') }}">Volver a campañas</a>@endsection

@section('content')
<style>
    .campaign-builder{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px;align-items:start}.builder-section{padding:20px}.builder-section h2{margin:0 0 4px;font-size:18px}.builder-section>p{margin:0 0 16px;color:var(--muted)}.builder-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.builder-fields .wide{grid-column:1/-1}.channel-choice,.segment-choice{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px solid var(--line);border-radius:8px;background:#fff}.channel-choice input[type="checkbox"],.segment-choice input[type="radio"]{width:16px;height:16px;min-height:0;padding:0;margin:2px 0 0;flex:0 0 16px;accent-color:var(--brand);cursor:pointer}.choice-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}.campaign-preview{position:sticky;top:18px}.price-preview{font-size:28px;color:var(--brand);font-weight:900}.old-price{text-decoration:line-through;color:var(--muted);margin-right:8px}.safe-note{padding:12px;border-radius:8px;background:#f0fdf4;color:#166534;font-size:12px;line-height:1.45}@media(max-width:950px){.campaign-builder{grid-template-columns:1fr}.campaign-preview{position:static}.builder-fields,.choice-grid{grid-template-columns:1fr}}
</style>

@if($services->isEmpty())
<div class="card" style="border-color:#fde68a;background:#fffbeb;color:#92400e;"><b>Primero necesitas crear un servicio activo.</b> La oferta debe estar asociada a un servicio con precio. <a href="/personal/servicios" style="text-decoration:underline;">Ir a Servicios</a>.</div>
@else
<form method="POST" action="{{ route('campaigns.store') }}" class="campaign-builder">
@csrf
<div style="display:grid;gap:14px;">
    @if($errors->any())<div class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">Revisa los campos marcados antes de continuar.</div>@endif
    <section class="card builder-section">
        <h2>1. Oferta</h2><p>Elige el servicio, descuento y tiempo disponible.</p>
        <div class="builder-fields">
            <div class="wide"><label for="name">Nombre interno</label><input id="name" name="name" value="{{ old('name','Oferta flash de hoy') }}" required>@error('name')<div class="danger">{{ $message }}</div>@enderror</div>
            <div><label for="service_id">Servicio</label><select id="service_id" name="service_id" required>@foreach($services as $service)<option value="{{ $service->id }}" data-price="{{ $service->price_cents }}" @selected(old('service_id')==$service->id)>{{ $service->name }}{{ $service->price_cents!==null?' · '.number_format($service->price_cents/100,2).' €':'' }}</option>@endforeach</select></div>
            <div><label for="discount_percent">Descuento</label><input id="discount_percent" name="discount_percent" type="number" min="1" max="90" value="{{ old('discount_percent',20) }}" required><div class="hint">Entre 1% y 90%.</div></div>
            <div class="wide"><label for="expires_at">Fecha y hora de vencimiento</label><input id="expires_at" name="expires_at" type="datetime-local" min="{{ now($clinic->localTimezone())->addMinutes(10)->format('Y-m-d\TH:i') }}" value="{{ old('expires_at',now($clinic->localTimezone())->addDay()->setTime(20,0)->format('Y-m-d\TH:i')) }}" required></div>
        </div>
    </section>
    <section class="card builder-section">
        <h2>2. Destinatarios</h2><p>La campaña solo incluirá clientes con permiso comercial para el canal elegido.</p>
        <div class="choice-grid">
            @foreach(['all'=>['Todos','Todos los clientes elegibles.'],'inactive'=>['Inactivos','Sin visitas recientes en 90 días.'],'frequent'=>['Habituales','Al menos 3 visitas en el último año.'],'vip'=>['VIP','Clientes marcados como VIP.']] as $value=>$copy)
            <label class="segment-choice"><input type="radio" name="segment" value="{{ $value }}" @checked(old('segment','all')===$value)><span><b>{{ $copy[0] }}</b><small style="display:block;color:var(--muted);margin-top:3px;">{{ $copy[1] }}</small></span></label>
            @endforeach
        </div>
    </section>
    <section class="card builder-section">
        <h2>3. Canales y mensaje</h2><p>Elige correo, SMS o ambos.</p>
        <div class="choice-grid" style="margin-bottom:14px;">
            <label class="channel-choice"><input type="checkbox" name="channels[]" value="email" @checked(in_array('email',old('channels',['email']),true))><span><b>Correo</b><small style="display:block;color:var(--muted);">Mensaje completo y botón de reserva.</small></span></label>
            <label class="channel-choice"><input type="checkbox" name="channels[]" value="sms" @checked(in_array('sms',old('channels',[]),true))><span><b>SMS</b><small style="display:block;color:var(--muted);">Aviso breve con enlace personal.</small></span></label>
        </div>
        @error('channels')<div class="danger" style="margin-bottom:10px;">{{ $message }}</div>@enderror
        <label for="subject">Asunto del correo</label><input id="subject" name="subject" value="{{ old('subject','Una oferta especial por tiempo limitado') }}">
        <label for="message" style="margin-top:12px;">Mensaje</label><textarea id="message" name="message" rows="5" maxlength="800" required>{{ old('message','Tenemos una oferta especial para ti. Aprovecha este descuento y reserva antes de que termine.') }}</textarea>
    </section>
    <button class="btn primary" type="submit">Previsualizar campaña</button>
</div>
<aside class="card builder-section campaign-preview">
    <small style="color:var(--brand);font-weight:900;">VISTA PREVIA DEL PRECIO</small>
    <h2 id="preview-service" style="margin-top:9px;">{{ $services->first()->name }}</h2>
    <p><span class="old-price" id="preview-old-price"></span><span class="price-preview" id="preview-price"></span></p>
    <p id="preview-discount" style="font-weight:800;"></p>
    <div class="safe-note"><b>Envío responsable</b><br>Solo se incluirán clientes que hayan autorizado promociones por correo o SMS. La siguiente pantalla mostrará el número exacto de destinatarios antes de confirmar.</div>
</aside>
</form>
<script>
(()=>{const service=document.getElementById('service_id'),discount=document.getElementById('discount_percent'),name=document.getElementById('preview-service'),old=document.getElementById('preview-old-price'),price=document.getElementById('preview-price'),copy=document.getElementById('preview-discount');const update=()=>{const option=service.options[service.selectedIndex],cents=Number(option.dataset.price||0),percent=Math.min(90,Math.max(0,Number(discount.value||0)));name.textContent=option.textContent.split(' · ')[0];old.textContent=cents?(cents/100).toFixed(2)+' €':'';price.textContent=cents?((cents*(100-percent)/10000).toFixed(2)+' €'):'Precio por definir';copy.textContent=percent+'% de descuento';};service.addEventListener('change',update);discount.addEventListener('input',update);update();})();
</script>
@endif
@endsection
