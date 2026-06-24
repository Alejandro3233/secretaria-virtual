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
    <div class="grid-2" style="margin-top:14px;">
        <div><label for="address">Dirección</label><input id="address" name="address" value="{{ old('address',$client->address) }}"></div>
        <div><label for="notification_preference">Avisos preferidos</label><select id="notification_preference" name="notification_preference">@foreach(['both'=>'SMS y email','sms'=>'Sólo SMS','email'=>'Sólo email','none'=>'Sin avisos'] as $value=>$label)<option value="{{ $value }}" @selected(old('notification_preference',$client->notification_preference ?: 'both')===$value)>{{ $label }}</option>@endforeach</select></div>
    </div>
    <div style="margin-top:14px;"><label for="notes">Notas internas</label><textarea id="notes" name="notes" rows="6" style="width:100%;border:1px solid #dbcbd4;border-radius:6px;padding:10px;resize:vertical;">{{ old('notes',$client->notes) }}</textarea></div>
    <div class="actions" style="justify-content:flex-end;margin-top:18px;"><a class="btn" href="{{ $client->exists ? route('clients.show',$client) : route('clients.index') }}">Cancelar</a><button class="btn primary" type="submit">{{ $client->exists ? 'Guardar cambios' : 'Crear cliente' }}</button></div>
</form>
@endsection
