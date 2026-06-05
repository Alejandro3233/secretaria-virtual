@extends('layouts.app')

@section('title', 'Nueva cita - Secretaria Virtual')
@section('page_title', 'Nueva cita')
@section('page_subtitle', 'Crea una cita en Secretaria Virtual. Si Google Calendar esta conectado, se sincroniza automaticamente.')
@section('page_actions')
    <a class="btn" href="/personal/servicios">Servicios</a>
    <a class="btn" href="/agenda">Volver a agenda</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            Revisa los campos marcados antes de guardar.
        </div>
    @endif

    <form method="POST" action="/agenda/nueva-cita" class="card">
        @csrf

        <div class="section-title">
            <h2>Datos de la cita</h2>
            <button class="btn primary" type="submit">Guardar cita</button>
        </div>

        <section class="grid-2">
            <div>
                <label for="client_id">Cliente existente</label>
                <select id="client_id" name="client_id">
                    <option value="">Crear cliente nuevo</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                            {{ $client->first_name }} {{ $client->last_name }} - {{ $client->phone }}
                        </option>
                    @endforeach
                </select>
                @error('client_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="status">Estado</label>
                <select id="status" name="status">
                    <option value="confirmed" @selected(old('status', 'confirmed') === 'confirmed')>Confirmada</option>
                    <option value="pending" @selected(old('status') === 'pending')>Pendiente</option>
                </select>
                @error('status') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="client_first_name">Nombre cliente nuevo</label>
                <input id="client_first_name" name="client_first_name" value="{{ old('client_first_name') }}" placeholder="Maria">
                @error('client_first_name') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="client_last_name">Apellido</label>
                <input id="client_last_name" name="client_last_name" value="{{ old('client_last_name') }}" placeholder="Lopez">
            </div>
            <div>
                <label for="client_phone">Telefono</label>
                <input id="client_phone" name="client_phone" value="{{ old('client_phone') }}" placeholder="+1 555 0142">
                @error('client_phone') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="client_email">Email</label>
                <input id="client_email" name="client_email" type="email" value="{{ old('client_email') }}" placeholder="cliente@email.com">
                @error('client_email') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="service_id">Servicio</label>
                <select id="service_id" name="service_id">
                    <option value="">Sin servicio</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" data-duration="{{ $service->duration_minutes }}" data-price="{{ $service->price_cents !== null ? number_format($service->price_cents / 100, 2, '.', '') : '' }}" @selected(old('service_id') == $service->id)>
                            {{ $service->name }} - {{ $service->duration_minutes }} min{{ $service->price_cents !== null ? ' - $'.number_format($service->price_cents / 100, 2) : '' }}
                        </option>
                    @endforeach
                </select>
                <span id="service_summary" class="subtitle" style="display:block;margin-top:8px;">Elige un servicio para cargar duracion y costo.</span>
                @error('service_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="stylist_id">Estilista</label>
                <select id="stylist_id" name="stylist_id">
                    <option value="">Sin asignar</option>
                    @foreach ($stylists as $stylist)
                        <option value="{{ $stylist->id }}" @selected(old('stylist_id') == $stylist->id)>
                            {{ $stylist->name }}{{ $stylist->specialty ? ' - '.$stylist->specialty : '' }}
                        </option>
                    @endforeach
                </select>
                @error('stylist_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="starts_at">Fecha y hora</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', now()->addHour()->format('Y-m-d\TH:i')) }}" required>
                @error('starts_at') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="duration_minutes">Duracion</label>
                <input id="duration_minutes" name="duration_minutes" type="number" min="15" max="480" step="15" value="{{ old('duration_minutes', 60) }}" required>
                @error('duration_minutes') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="chair_station">Estacion</label>
                <input id="chair_station" name="chair_station" value="{{ old('chair_station') }}" placeholder="Silla 2, Cabina 1">
            </div>
        </section>

        <section class="grid-2" style="margin-top:18px;">
            <div>
                <label for="reason">Motivo / titulo</label>
                <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Color raiz + blower">
            </div>
            <div>
                <label for="client_comments">Comentarios del cliente</label>
                <input id="client_comments" name="client_comments" value="{{ old('client_comments') }}" placeholder="Prefiere Sofia, llegar 10 minutos antes">
            </div>
        </section>

        <section style="margin-top:18px;">
            <label for="internal_notes">Notas internas</label>
            <input id="internal_notes" name="internal_notes" value="{{ old('internal_notes') }}" placeholder="Formula, alergias, deposito, observaciones">
        </section>

        <button class="btn primary" type="submit" style="margin-top:22px;">Guardar cita</button>
    </form>

    <script>
        const serviceSelect = document.getElementById('service_id');
        const durationInput = document.getElementById('duration_minutes');
        const reasonInput = document.getElementById('reason');
        const serviceSummary = document.getElementById('service_summary');

        const syncServiceFields = () => {
            const option = serviceSelect.options[serviceSelect.selectedIndex];
            if (option?.dataset.duration) {
                durationInput.value = option.dataset.duration;
            }
            if (option?.value && !reasonInput.value) {
                reasonInput.value = option.textContent.split(' - ')[0].trim();
            }
            if (option?.value) {
                const price = option.dataset.price ? `$${option.dataset.price}` : 'Sin costo definido';
                serviceSummary.textContent = `${option.dataset.duration || durationInput.value} min - ${price}`;
            } else {
                serviceSummary.textContent = 'Elige un servicio para cargar duracion y costo.';
            }
        };

        serviceSelect?.addEventListener('change', syncServiceFields);
        syncServiceFields();
    </script>
@endsection
