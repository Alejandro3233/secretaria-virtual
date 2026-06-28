@extends('layouts.app')

@section('title', ($client->exists ? 'Editar' : 'Nuevo').' cliente - Secretary365')
@section('page_title', $client->exists ? 'Editar cliente' : 'Nuevo cliente')
@section('page_subtitle', 'Información de contacto y preferencias del cliente.')

@section('content')
<form class="card" method="POST" action="{{ $client->exists ? route('clients.update',$client) : route('clients.store') }}" style="max-width:900px;margin:auto;">
    @csrf @if($client->exists) @method('PUT') @endif
    <div class="grid-2">
        <div><label for="first_name">Nombre</label><input id="first_name" name="first_name" value="{{ old('first_name',$client->first_name) }}" required>@error('first_name')<div class="danger" style="margin-top:6px;">{{ $message }}</div>@enderror</div>
        <div><label for="last_name">Apellidos</label><input id="last_name" name="last_name" value="{{ old('last_name',$client->last_name) }}"></div>
    </div>
    <div class="grid-2" style="margin-top:14px;">
        <div><label for="phone">Teléfono</label><input id="phone" name="phone" value="{{ old('phone',$client->phone) }}" required autocomplete="tel">@error('phone')<div class="danger" style="margin-top:6px;">{{ $message }}</div>@enderror</div>
        <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email',$client->email) }}" autocomplete="email">@error('email')<div class="danger" style="margin-top:6px;">{{ $message }}</div>@enderror</div>
    </div>
    <section style="margin-top:18px;padding:14px;border:1px solid var(--line);border-radius:8px;background:var(--soft);">
        <b>Permisos para promociones</b>
        <p class="subtitle" style="margin:5px 0 12px;">Marca solamente los canales en los que el cliente acepto recibir ofertas comerciales.</p>
        <div class="grid-2">
            <label style="display:flex;align-items:center;gap:9px;margin:0;font-weight:700;"><input type="hidden" name="marketing_email_consent" value="0"><input type="checkbox" name="marketing_email_consent" value="1" style="width:auto;" @checked(old('marketing_email_consent', (bool) $client->marketing_email_consent_at))> Acepta ofertas por correo</label>
            <label style="display:flex;align-items:center;gap:9px;margin:0;font-weight:700;"><input type="hidden" name="marketing_sms_consent" value="0"><input type="checkbox" name="marketing_sms_consent" value="1" style="width:auto;" @checked(old('marketing_sms_consent', (bool) $client->marketing_sms_consent_at))> Acepta ofertas por SMS</label>
        </div>
    </section>
    <div class="grid-2" style="margin-top:14px;">
        <div><label for="address">Dirección</label><input id="address" name="address" value="{{ old('address',$client->address) }}"></div>
        <div><label for="notification_preference">Avisos preferidos</label><select id="notification_preference" name="notification_preference">@foreach(['both'=>'SMS y email','sms'=>'Sólo SMS','email'=>'Sólo email','none'=>'Sin avisos'] as $value=>$label)<option value="{{ $value }}" @selected(old('notification_preference',$client->notification_preference ?: 'both')===$value)>{{ $label }}</option>@endforeach</select></div>
    </div>
    <div style="margin-top:14px;"><label for="notes">Notas internas</label><textarea id="notes" name="notes" rows="6" style="width:100%;border:1px solid #dbcbd4;border-radius:6px;padding:10px;resize:vertical;">{{ old('notes',$client->notes) }}</textarea></div>
    <div class="actions" style="justify-content:flex-end;margin-top:18px;"><a class="btn" href="{{ $client->exists ? route('clients.show',$client) : route('clients.index') }}">Cancelar</a><button class="btn primary" type="submit">{{ $client->exists ? 'Guardar cambios' : 'Crear cliente' }}</button></div>
</form>
@endsection
