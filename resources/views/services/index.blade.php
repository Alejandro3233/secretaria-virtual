@extends('layouts.app')

@section('title', 'Servicios - Secretaria Virtual')
@section('page_title', 'Servicios')
@section('page_subtitle', 'Configura servicios de salon con costo, duracion y estado para usarlos al crear citas.')
@section('page_actions')
    <a class="btn" href="/personal">Personal</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    @if (session('service_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('service_status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            Revisa los campos marcados antes de guardar.
        </div>
    @endif

    <section class="grid-2" style="margin-bottom:18px;">
        <form method="POST" action="/personal/servicios" class="card">
            @csrf

            <div class="section-title">
                <h2>Agregar servicio</h2>
                <button class="btn primary" type="submit">Guardar servicio</button>
            </div>

            <section class="grid-2">
                <div>
                    <label for="name">Nombre del servicio</label>
                    <input id="name" name="name" value="{{ old('name') }}" placeholder="Color raiz + blower" required>
                    @error('name') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="duration_minutes">Duracion</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="15" max="480" step="15" value="{{ old('duration_minutes', 60) }}" required>
                    @error('duration_minutes') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                </div>
            </section>

            <section class="grid-2" style="margin-top:18px;">
                <div>
                    <label for="price">Costo</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price') }}" placeholder="75.00">
                    @error('price') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label>Estado</label>
                    <input type="hidden" name="is_active" value="0">
                    <label style="display:flex;align-items:center;gap:10px;min-height:42px;margin:0;color:var(--ink);">
                        <input type="checkbox" name="is_active" value="1" checked style="width:auto;min-height:auto;">
                        Activo
                    </label>
                </div>
            </section>
        </form>

        <article class="card">
            <div class="section-title">
                <h2>Opciones rapidas</h2>
                <form method="POST" action="/personal/servicios/catalogo-base">
                    @csrf
                    <button class="btn primary" type="submit">Cargar catalogo base</button>
                </form>
            </div>

            <div class="actions" style="gap:8px;">
                @foreach ($serviceTemplates as $template)
                    <button class="btn service-template" type="button" data-name="{{ $template['name'] }}" data-duration="{{ $template['duration'] }}" data-price="{{ number_format($template['price'] / 100, 2, '.', '') }}">
                        {{ $template['name'] }}
                    </button>
                @endforeach
            </div>
        </article>
    </section>

    <article class="card">
        <div class="section-title">
            <h2>Servicios registrados</h2>
            <span class="subtitle">{{ $services->count() }} servicios</span>
        </div>

        @if ($services->isEmpty())
            <span class="subtitle">Todavia no hay servicios agregados para este salon.</span>
        @else
            @foreach ($services as $service)
                <form id="service-form-{{ $service->id }}" method="POST" action="/personal/servicios/{{ $service->id }}">
                    @csrf
                    @method('PUT')
                </form>
            @endforeach

            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Duracion</th>
                        <th>Costo</th>
                        <th>Estado</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr>
                            <td>
                                <input form="service-form-{{ $service->id }}" name="name" value="{{ old('name', $service->name) }}" required>
                            </td>
                            <td>
                                <input form="service-form-{{ $service->id }}" name="duration_minutes" type="number" min="15" max="480" step="15" value="{{ old('duration_minutes', $service->duration_minutes) }}" required>
                            </td>
                            <td>
                                <input form="service-form-{{ $service->id }}" name="price" type="number" min="0" step="0.01" value="{{ old('price', $service->price_cents !== null ? number_format($service->price_cents / 100, 2, '.', '') : '') }}" placeholder="0.00">
                            </td>
                            <td>
                                <input form="service-form-{{ $service->id }}" type="hidden" name="is_active" value="0">
                                <label style="display:flex;align-items:center;gap:10px;margin:0;color:var(--ink);">
                                    <input form="service-form-{{ $service->id }}" type="checkbox" name="is_active" value="1" @checked($service->is_active) style="width:auto;min-height:auto;">
                                    Activo
                                </label>
                            </td>
                            <td>
                                <button form="service-form-{{ $service->id }}" class="btn primary" type="submit">Actualizar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </article>

    <script>
        const nameInput = document.getElementById('name');
        const durationInput = document.getElementById('duration_minutes');
        const priceInput = document.getElementById('price');

        document.querySelectorAll('.service-template').forEach((button) => {
            button.addEventListener('click', () => {
                nameInput.value = button.dataset.name || '';
                durationInput.value = button.dataset.duration || 60;
                priceInput.value = button.dataset.price || '';
                nameInput.focus();
            });
        });
    </script>
@endsection
