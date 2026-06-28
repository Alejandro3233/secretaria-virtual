@extends('layouts.app')

@section('title', 'Servicios - Secretary365')
@section('page_title', 'Servicios')
@section('page_subtitle', 'Configura servicios de salon con costo, duracion y estado para usarlos al crear citas.')
@section('page_actions')
    <a class="btn" href="/personal">Personal</a>
    <a class="btn primary" href="/agenda/nueva-cita">Nueva cita</a>
@endsection

@section('content')
    <style>
        .service-image { width:80px;height:60px;display:grid;place-items:center;overflow:hidden;border-radius:12px;background:#f3e8ee;color:var(--brand);font-size:11px;font-weight:900;text-align:center;box-shadow:0 0 0 2px #fff,0 0 0 3px var(--line);transition:box-shadow .15s ease,transform .15s ease; }
        .service-image img { width:100%;height:100%;object-fit:cover; }
        .service-image-field { display:flex;align-items:center;gap:12px;min-width:155px; }
        .service-image-edit { display:inline-grid;cursor:pointer;margin:0; }
        .service-image-edit:hover .service-image,.service-image-edit:focus-within .service-image { box-shadow:0 0 0 3px #f9a8d4;transform:scale(1.03); }
        .service-image-edit input[type="file"] { position:absolute;width:1px;height:1px;opacity:0;pointer-events:none; }
        .service-image-picker { position:relative; }
        .service-image-picker>summary { cursor:pointer;list-style:none; }
        .service-image-picker>summary::-webkit-details-marker { display:none; }
        .service-image-picker-panel { position:absolute;z-index:90;top:calc(100% + 8px);left:0;width:210px;padding:10px;border:1px solid var(--line);border-radius:9px;background:#fff;box-shadow:0 14px 34px rgba(24,18,22,.2); }
        .service-sample-image { width:100%;padding:0;overflow:hidden;border:2px solid transparent;border-radius:8px;background:#fff;cursor:pointer; }
        .service-sample-image:hover { border-color:var(--brand); }
        .service-sample-image img { width:100%;aspect-ratio:4/3;display:block;object-fit:cover; }
        .service-own-image { min-height:36px;display:flex;align-items:center;justify-content:center;margin-top:8px;border:1px solid var(--line);border-radius:6px;color:var(--brand);font-size:12px;font-weight:900;cursor:pointer; }
        .service-own-image input { position:absolute;width:1px;height:1px;opacity:0;pointer-events:none; }
        .service-image-hint { max-width:78px;color:var(--muted);font-size:11px;font-weight:800;line-height:1.25; }
        .service-addons-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px; }
        .service-addon-list { display:grid;gap:8px;margin:12px 0; }
        .service-addon-row { display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 10px;border:1px solid var(--line);border-radius:8px;background:var(--soft); }
        .addon-suggestions { display:flex;flex-wrap:wrap;gap:6px;margin-top:10px; }
        .addon-suggestion { border:1px dashed var(--line);border-radius:999px;padding:5px 9px;background:#fff;color:var(--muted);font-size:11px;font-weight:800;cursor:pointer; }
        .service-extra-actions { display:flex;align-items:center;gap:7px;flex-wrap:wrap; }
        .service-addon-card:target { border-color:var(--brand) !important;box-shadow:0 0 0 3px rgba(192,38,90,.14); }
        .services-card-grid { display:grid;grid-template-columns:1fr;gap:12px;margin-top:16px; }
        .service-manager-card { display:grid;grid-template-columns:minmax(285px,1fr) minmax(405px,1.45fr) minmax(300px,.8fr);align-items:center;gap:18px;padding:16px 18px;border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:0 8px 24px rgba(36,21,29,.05); }
        .service-manager-head,.service-manager-footer { display:flex;align-items:center;justify-content:space-between;gap:14px; }
        .service-manager-head { margin:0; }
        .service-manager-identity { display:flex;align-items:center;gap:13px;min-width:0; }
        .service-manager-identity h3 { margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .service-manager-fields { display:grid;grid-template-columns:minmax(110px,.65fr) 120px 145px;gap:10px;align-items:end; }
        .service-manager-footer { align-self:stretch;flex-direction:row;align-items:center;justify-content:flex-end;margin:0; }
        .service-manager-footer .btn { width:auto;min-width:110px; }
        .service-name-controls { display:grid;grid-template-columns:1fr;gap:7px;align-items:center; }
        .service-extras-panel { position:relative;margin:0;padding:0;border:0; }
        .service-extras-panel>summary { min-height:40px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 10px;border:1px solid #dbcbd4;border-radius:6px;background:#fff;cursor:pointer;list-style:none;color:var(--ink);font-size:13px;font-weight:900; }
        .service-extras-panel>summary::-webkit-details-marker { display:none; }
        .service-extras-panel>summary>span:first-child { display:none; }
        .extras-config-trigger { display:inline-flex;align-items:center;min-height:36px;padding:0 11px;border:0;border-radius:5px;background:#fff1f6;color:var(--brand);font-size:12px;font-weight:900;white-space:nowrap; }
        .service-extras-panel>summary:hover .extras-config-trigger { background:#111827;color:#fff; }
        .service-extras-panel[open] { z-index:80; }
        .extras-modal-backdrop { display:none; }
        .extras-modal-dialog { position:absolute;z-index:81;top:calc(100% + 6px);right:0;width:min(680px,calc(100vw - 130px));max-height:68vh;overflow:auto;padding:18px;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 16px 38px rgba(24,18,22,.2); }
        .extras-modal-head { position:sticky;top:-18px;z-index:2;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:-18px -18px 14px;padding:17px 18px 12px;border-bottom:1px solid var(--line);background:#fff; }
        .extras-modal-head h2 { margin:0; }
        .extras-modal-close { width:38px;height:38px;display:grid;place-items:center;flex:0 0 38px;border:1px solid var(--line);border-radius:50%;background:#fff;color:var(--ink);font-size:22px;cursor:pointer; }
        @media(max-width:820px){.service-manager-card{grid-template-columns:1fr}.service-manager-fields{grid-template-columns:1fr}.service-manager-head{align-items:flex-start}.service-manager-footer{align-items:stretch;flex-direction:column}.service-manager-footer .btn{width:100%}.extras-modal-dialog{position:fixed;inset:12px;width:auto;max-height:none}}
    </style>
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
        <form method="POST" action="/personal/servicios" class="card" enctype="multipart/form-data">
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
                <form id="service-form-{{ $service->id }}" method="POST" action="/personal/servicios/{{ $service->id }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                </form>
            @endforeach

            <div class="services-card-grid">
                @foreach ($services as $service)
                    <article id="service-{{ $service->id }}" class="service-manager-card" style="scroll-margin-top:18px;">
                        <div class="service-manager-head">
                            <div class="service-manager-identity">
                                <details class="service-image-picker">
                                    <summary title="Elegir foto de {{ $service->name }}"><span class="service-image" data-service-image-preview>@if($service->imageUrl())<img src="{{ $service->imageUrl() }}" alt="Resultado de {{ $service->name }}">@else{{ mb_strtoupper(mb_substr($service->name, 0, 2)) }}@endif</span></summary>
                                    <div class="service-image-picker-panel">
                                        @if($sampleImage = ($serviceSampleImages[$service->name] ?? null))
                                            <small style="display:block;margin-bottom:6px;font-weight:900;">Foto de muestra</small>
                                            <button class="service-sample-image" type="button" data-service-sample="{{ $sampleImage }}"><img src="{{ asset('images/service-samples/'.$sampleImage) }}" alt="Muestra para {{ $service->name }}"></button>
                                        @endif
                                        <label class="service-own-image">Subir mi propia foto<input form="service-form-{{ $service->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Subir foto de {{ $service->name }}" data-service-image-input></label>
                                        <input form="service-form-{{ $service->id }}" type="hidden" name="sample_image" value="" data-service-sample-input>
                                    </div>
                                </details>
                                <div><h3>{{ $service->name }}</h3></div>
                            </div>
                        </div>
                        <div class="service-manager-fields">
                            <div>
                                <input form="service-form-{{ $service->id }}" type="hidden" name="name" value="{{ $service->name }}">
                                <label>Complementos</label>
                                <div class="service-name-controls" data-extras-anchor></div>
                            </div>
                            <div><label>Duracion</label><input form="service-form-{{ $service->id }}" name="duration_minutes" type="number" min="15" max="480" step="15" value="{{ $service->duration_minutes }}" required></div>
                            <div><label>Costo</label><input form="service-form-{{ $service->id }}" name="price" type="number" min="0" step="0.01" value="{{ $service->price_cents !== null ? number_format($service->price_cents / 100, 2, '.', '') : '' }}" placeholder="0.00"></div>
                        </div>
                        <div class="service-manager-footer">
                            <label style="display:flex;align-items:center;gap:8px;margin:0;"><input form="service-form-{{ $service->id }}" type="hidden" name="is_active" value="0"><input form="service-form-{{ $service->id }}" type="checkbox" name="is_active" value="1" @checked($service->is_active) style="width:auto;"> Visible para clientes</label>
                            <button form="service-form-{{ $service->id }}" class="btn primary" type="submit">Actualizar</button>
                        </div>

                        <details class="service-extras-panel" @if((int) request('extras') === $service->id) open @endif>
                            <summary aria-label="Gestionar complementos de {{ $service->name }}"><span>Complementos opcionales</span><span class="extras-config-trigger">{{ $service->addons->count() }} configurados</span></summary>
                            <div class="extras-modal-backdrop" data-extras-close></div>
                            <div class="extras-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="extras-title-{{ $service->id }}">
                                <div class="extras-modal-head">
                                    <div><h2 id="extras-title-{{ $service->id }}">Complementos de {{ $service->name }}</h2><span class="subtitle">Añade opciones que el cliente puede elegir junto al servicio.</span></div>
                                    <button class="extras-modal-close" type="button" aria-label="Cerrar ventana" data-extras-close>&times;</button>
                                </div>
                            <div class="service-addon-list">
                                @forelse($service->addons as $addon)
                                    <div class="service-addon-row" style="display:block;">
                                        <form method="POST" action="/personal/servicios/{{ $service->id }}/extras/{{ $addon->id }}" class="grid-2" style="align-items:end;">
                                            @csrf @method('PUT')
                                            <div><label>Extra</label><input name="addon_name" value="{{ $addon->name }}" required></div>
                                            <div><label>Precio</label><input name="addon_price" type="number" min="0" step="0.01" value="{{ number_format($addon->price_cents / 100, 2, '.', '') }}" required></div>
                                            <label style="display:flex;align-items:center;gap:7px;margin:0;"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked($addon->is_active)> Visible</label>
                                            <button class="btn" type="submit">Guardar</button>
                                        </form>
                                        <form method="POST" action="/personal/servicios/{{ $service->id }}/extras/{{ $addon->id }}" onsubmit="return confirm('¿Eliminar este extra?');">@csrf @method('DELETE')<button class="btn" type="submit" style="margin-top:8px;">Eliminar</button></form>
                                    </div>
                                @empty
                                    <span class="subtitle">Este servicio aun no tiene extras.</span>
                                @endforelse
                            </div>
                            <form method="POST" action="/personal/servicios/{{ $service->id }}/extras" class="grid-2" data-addon-form>
                                @csrf
                                <div><label>Nuevo extra</label><input name="addon_name" placeholder="Corte de cuticula" required></div>
                                <div><label>Precio adicional</label><input name="addon_price" type="number" min="0" step="0.01" placeholder="1.00" required></div>
                                <button class="btn primary" type="submit">Agregar extra</button>
                            </form>
                            @php($suggestions = $addonSuggestions[$service->name] ?? $addonSuggestions['*'])
                            <div class="addon-suggestions">
                                @foreach($suggestions as [$suggestionName, $suggestionPrice])
                                    <button class="addon-suggestion" type="button" data-addon-name="{{ $suggestionName }}" data-addon-price="{{ number_format($suggestionPrice, 2, '.', '') }}">{{ $suggestionName }} +{{ number_format($suggestionPrice, 2) }}</button>
                                @endforeach
                            </div>
                            </div>
                        </details>
                    </article>
                @endforeach
            </div>

            <table style="display:none;">
                <thead>
                    <tr>
                        <th>Foto</th>
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
                                <div class="service-image-field">
                                    <label class="service-image-edit" title="Cambiar foto de {{ $service->name }}">
                                        <span class="service-image" data-service-image-preview>@if($service->imageUrl())<img src="{{ $service->imageUrl() }}" alt="Resultado de {{ $service->name }}">@else{{ mb_strtoupper(mb_substr($service->name, 0, 2)) }}@endif</span>
                                        <input form="service-form-{{ $service->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Cambiar foto de {{ $service->name }}" data-service-image-input>
                                    </label>
                                    <span class="service-image-hint">Toca para cambiar</span>
                                </div>
                            </td>
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
                                <div class="service-extra-actions">
                                    <button form="service-form-{{ $service->id }}" class="btn primary" type="submit">Actualizar</button>
                                    <a class="btn" href="#extras-{{ $service->id }}">Extras ({{ $service->addons->count() }})</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </article>

    @if ($services->isNotEmpty())
        <section id="service-extras" class="card" style="display:none;margin-top:18px;scroll-margin-top:18px;">
            <div class="section-title">
                <div><h2>Extras de cada servicio</h2><span class="subtitle">Complementos opcionales que el cliente podra agregar durante su reserva.</span></div>
            </div>
            <div class="service-addons-grid">
                @foreach($services as $service)
                    <article id="extras-{{ $service->id }}" class="service-addon-card" style="padding:14px;border:1px solid var(--line);border-radius:10px;scroll-margin-top:18px;">
                        <h3 style="margin:0;">{{ $service->name }}</h3>
                        <div class="service-addon-list">
                            @forelse($service->addons as $addon)
                                <div class="service-addon-row" style="display:block;">
                                    <form method="POST" action="/personal/servicios/{{ $service->id }}/extras/{{ $addon->id }}" class="grid-2" style="align-items:end;">
                                        @csrf @method('PUT')
                                        <div><label>Extra</label><input name="addon_name" value="{{ $addon->name }}" required></div>
                                        <div><label>Precio</label><input name="addon_price" type="number" min="0" step="0.01" value="{{ number_format($addon->price_cents / 100, 2, '.', '') }}" required></div>
                                        <label style="display:flex;align-items:center;gap:7px;margin:0;"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked($addon->is_active)> Visible al cliente</label>
                                        <button class="btn" type="submit">Guardar</button>
                                    </form>
                                    <form method="POST" action="/personal/servicios/{{ $service->id }}/extras/{{ $addon->id }}" onsubmit="return confirm('¿Eliminar este extra?');">
                                        @csrf @method('DELETE')
                                        <button class="btn" type="submit" style="margin-top:8px;">Eliminar</button>
                                    </form>
                                </div>
                            @empty
                                <span class="subtitle">Este servicio aun no tiene extras.</span>
                            @endforelse
                        </div>
                        <form method="POST" action="/personal/servicios/{{ $service->id }}/extras" class="grid-2" data-addon-form>
                            @csrf
                            <div><label>Nombre del extra</label><input name="addon_name" placeholder="Corte de cuticula" required></div>
                            <div><label>Precio adicional</label><input name="addon_price" type="number" min="0" step="0.01" placeholder="1.00" required></div>
                            <button class="btn primary" type="submit">Agregar extra</button>
                        </form>
                        @php($suggestions = $addonSuggestions[$service->name] ?? $addonSuggestions['*'])
                        <div class="addon-suggestions" aria-label="Ejemplos de extras">
                            @foreach($suggestions as [$suggestionName, $suggestionPrice])
                                <button class="addon-suggestion" type="button" data-addon-name="{{ $suggestionName }}" data-addon-price="{{ number_format($suggestionPrice, 2, '.', '') }}">{{ $suggestionName }} +{{ number_format($suggestionPrice, 2) }}</button>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

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

        document.querySelectorAll('[data-service-image-input]').forEach((input) => input.addEventListener('change', () => {
            const file = input.files?.[0];
            const preview = input.closest('.service-image-picker')?.querySelector('[data-service-image-preview]')
                || input.closest('.service-image-edit')?.querySelector('[data-service-image-preview]')
                || input.closest('.service-image-field')?.querySelector('[data-service-image-preview]');
            if (!file || !preview) return;
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = 'Vista previa del servicio';
            preview.replaceChildren(image);
            const sampleInput = input.closest('.service-image-picker')?.querySelector('[data-service-sample-input]');
            if (sampleInput) sampleInput.value = '';
        }));

        document.querySelectorAll('[data-service-sample]').forEach((button) => button.addEventListener('click', () => {
            const picker = button.closest('.service-image-picker');
            picker.querySelector('[data-service-sample-input]').value = button.dataset.serviceSample;
            picker.querySelector('[data-service-image-input]').value = '';
            const image = button.querySelector('img').cloneNode();
            image.alt = 'Vista previa de la foto de muestra';
            picker.querySelector('[data-service-image-preview]').replaceChildren(image);
            picker.removeAttribute('open');
        }));

        document.querySelectorAll('.addon-suggestion').forEach((button) => button.addEventListener('click', () => {
            const form = button.closest('article').querySelector('[data-addon-form]');
            form.elements.addon_name.value = button.dataset.addonName;
            form.elements.addon_price.value = button.dataset.addonPrice;
            form.elements.addon_name.focus();
        }));

        const extrasPanels = document.querySelectorAll('.service-extras-panel');
        extrasPanels.forEach((panel) => {
            const anchor = panel.closest('.service-manager-card')?.querySelector('[data-extras-anchor]');
            if (anchor) anchor.appendChild(panel);
        });
        const syncModalState = () => document.body.style.overflow = '';
        extrasPanels.forEach((panel) => {
            panel.addEventListener('toggle', syncModalState);
            panel.querySelectorAll('[data-extras-close]').forEach((button) => button.addEventListener('click', () => {
                panel.removeAttribute('open');
                if (window.location.search.includes('extras=')) history.replaceState({}, '', window.location.pathname);
                syncModalState();
            }));
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            const panel = document.querySelector('.service-extras-panel[open]');
            if (panel) panel.removeAttribute('open');
            if (window.location.search.includes('extras=')) history.replaceState({}, '', window.location.pathname);
            syncModalState();
        });
        document.addEventListener('click', (event) => {
            extrasPanels.forEach((panel) => {
                if (panel.open && !panel.contains(event.target)) panel.removeAttribute('open');
            });
        });
        syncModalState();
    </script>
@endsection
