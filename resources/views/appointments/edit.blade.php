@extends('layouts.app')

@section('title', 'Editar cita - Secretaria Virtual')
@section('page_title', 'Editar cita')
@section('page_subtitle', 'Cambia la fecha, hora o detalles de la cita. Si Google Calendar esta conectado, se actualiza automaticamente.')
@section('page_actions')
    <a class="btn" href="/citas">Volver a citas</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            Revisa los campos marcados antes de guardar.
        </div>
    @endif

    @if (session('appointment_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('appointment_error') }}
        </div>
    @endif

    <form method="POST" action="/citas/{{ $appointment->id }}" class="card">
        @csrf
        @method('PUT')

        <div class="section-title">
            <div>
                <h2>{{ trim(($appointment->client?->first_name ?? 'Cliente').' '.($appointment->client?->last_name ?? '')) }}</h2>
                <span class="subtitle">{{ $appointment->client?->phone ?? 'Sin telefono' }}</span>
            </div>
            <button class="btn primary" type="submit">Guardar cambios</button>
        </div>

        <section class="grid-3">
            <div>
                <label for="starts_at">Fecha y hora</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $appointment->starts_at->format('Y-m-d\TH:i')) }}" required>
                @error('starts_at') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="duration_minutes">Duracion</label>
                <input id="duration_minutes" name="duration_minutes" type="number" min="15" max="480" step="15" value="{{ old('duration_minutes', $appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : 60) }}" required>
                @error('duration_minutes') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="status">Estado</label>
                <select id="status" name="status">
                    <option value="confirmed" @selected(old('status', $appointment->status) === 'confirmed')>Confirmada</option>
                    <option value="pending" @selected(old('status', $appointment->status) === 'pending')>Pendiente</option>
                    <option value="cancelled" @selected(in_array(old('status', $appointment->status), ['cancelled', 'canceled'], true))>Cancelada</option>
                </select>
                @error('status') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="service_id">Servicio</label>
                <select id="service_id" name="service_id">
                    <option value="">Sin servicio</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" data-duration="{{ $service->duration_minutes }}" @selected(old('service_id', $appointment->service_id) == $service->id)>
                            {{ $service->name }} - {{ $service->duration_minutes }} min
                        </option>
                    @endforeach
                </select>
                @error('service_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="stylist_id">Estilista</label>
                <select id="stylist_id" name="stylist_id">
                    <option value="">Sin asignar</option>
                    @foreach ($stylists as $stylist)
                        <option value="{{ $stylist->id }}" @selected(old('stylist_id', $appointment->stylist_id) == $stylist->id)>
                            {{ $stylist->name }}{{ $stylist->specialty ? ' - '.$stylist->specialty : '' }}
                        </option>
                    @endforeach
                </select>
                @error('stylist_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="chair_station">Estacion</label>
                <input id="chair_station" name="chair_station" value="{{ old('chair_station', $appointment->chair_station) }}" placeholder="Silla 2, Cabina 1">
            </div>
        </section>

        <section class="grid-2" style="margin-top:18px;">
            <div>
                <label for="reason">Motivo / titulo</label>
                <input id="reason" name="reason" value="{{ old('reason', $appointment->reason) }}" placeholder="Color raiz + blower">
            </div>
            <div>
                <label for="client_comments">Comentarios del cliente</label>
                <input id="client_comments" name="client_comments" value="{{ old('client_comments', $appointment->client_comments) }}" placeholder="Prefiere Sofia, llegar 10 minutos antes">
            </div>
        </section>

        <section style="margin-top:18px;">
            <label for="internal_notes">Notas internas</label>
            <input id="internal_notes" name="internal_notes" value="{{ old('internal_notes', $appointment->internal_notes) }}" placeholder="Formula, alergias, deposito, observaciones">
        </section>

        <div class="actions" style="margin-top:22px;">
            <button class="btn primary" type="submit">Guardar cambios</button>
            <a class="btn" href="/citas">Volver</a>
        </div>
    </form>

    @if (! in_array($appointment->status, ['cancelled', 'canceled'], true))
        <form method="POST" action="/citas/{{ $appointment->id }}/cancelar" class="card" style="margin-top:18px;border-color:#d1d5db;" onsubmit="return confirm('Estas seguro de que quieres cancelar esta cita? Permanecera en el historial y se quitara de Google Calendar si estaba sincronizada.');">
            @csrf
            @method('PUT')
            <div class="section-title" style="margin-bottom:0;">
                <div>
                    <h2>Cancelar cita</h2>
                    <span class="subtitle">Conserva la cita en el historial con estado cancelado.</span>
                </div>
                <button class="btn" type="submit" style="border-color:#4b5563;color:#374151;">Cancelar cita</button>
            </div>
        </form>
    @else
        <div class="card" style="margin-top:18px;border-color:#d1d5db;background:#f3f4f6;color:#374151;font-weight:800;">
            Esta cita esta cancelada.
        </div>
    @endif

    <form method="POST" action="/citas/{{ $appointment->id }}" class="card" style="margin-top:18px;border-color:#fecaca;" onsubmit="return confirm('Estas seguro de que quieres eliminar esta cita? Esta accion quitara la cita de la agenda y de Google Calendar si estaba sincronizada.');">
        @csrf
        @method('DELETE')
        <div class="section-title" style="margin-bottom:0;">
            <div>
                <h2>Eliminar cita</h2>
                <span class="subtitle">Esto quita la cita de la lista y tambien de Google Calendar si estaba sincronizada.</span>
            </div>
            <button class="btn" type="submit">Eliminar</button>
        </div>
    </form>

    <script>
        const serviceSelect = document.getElementById('service_id');
        const durationInput = document.getElementById('duration_minutes');
        const reasonInput = document.getElementById('reason');

        serviceSelect?.addEventListener('change', () => {
            const option = serviceSelect.options[serviceSelect.selectedIndex];
            if (option?.dataset.duration) {
                durationInput.value = option.dataset.duration;
            }
            if (option?.value && !reasonInput.value) {
                reasonInput.value = option.textContent.split(' - ')[0].trim();
            }
        });
    </script>
@endsection
