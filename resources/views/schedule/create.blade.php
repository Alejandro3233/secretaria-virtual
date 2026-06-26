@extends('layouts.app')

@section('title', 'Nueva cita - Secretaria Virtual')
@section('page_title', 'Nueva cita')
@section('page_subtitle', 'Crea una cita en Secretaria Virtual. Si Google Calendar esta conectado, se sincroniza automaticamente.')
@section('page_actions')
    <a class="btn" href="/personal/servicios">Servicios</a>
    <a class="btn" href="/agenda">Volver a agenda</a>
@endsection

@section('content')
    <style>
        .client-lookup { position: relative; }
        .client-results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 20;
            display: none;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: white;
            box-shadow: 0 18px 38px rgba(24, 18, 22, .14);
        }
        .client-results.is-open { display: grid; }
        .client-result {
            width: 100%;
            min-height: 54px;
            display: grid;
            gap: 3px;
            border: 0;
            border-bottom: 1px solid #f2eaf0;
            padding: 10px 12px;
            background: white;
            color: var(--ink);
            text-align: left;
            cursor: pointer;
        }
        .client-result:last-child { border-bottom: 0; }
        .client-result:hover { background: #fffafd; }
        .client-result b { font-size: 14px; }
        .client-result span { color: var(--muted); font-size: 13px; }
    </style>

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
                        <option value="{{ $client->id }}" @selected(old('client_id', request('client_id')) == $client->id)>
                            {{ $client->first_name }} {{ $client->last_name }} - {{ $client->phone }}
                        </option>
                    @endforeach
                </select>
                @error('client_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>

            <div class="client-lookup">
                <label for="client_lookup">Buscar cliente por nombre o telefono</label>
                <input id="client_lookup" autocomplete="off" placeholder="Escribe Maria, Lopez o +1 555...">
                <div id="client_results" class="client-results" role="listbox" aria-label="Clientes encontrados"></div>
                <span id="client_lookup_hint" class="subtitle" style="display:block;margin-top:8px;">Si el cliente existe, seleccionalo para cargar sus datos.</span>
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
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', now($timezone)->addHour()->format('Y-m-d\TH:i')) }}" required>
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
        const clientSelect = document.getElementById('client_id');
        const clientLookup = document.getElementById('client_lookup');
        const clientResults = document.getElementById('client_results');
        const clientLookupHint = document.getElementById('client_lookup_hint');
        const clientFirstName = document.getElementById('client_first_name');
        const clientLastName = document.getElementById('client_last_name');
        const clientPhone = document.getElementById('client_phone');
        const clientEmail = document.getElementById('client_email');
        let clientLookupTimer = null;

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

        const closeClientResults = () => {
            clientResults.classList.remove('is-open');
            clientResults.innerHTML = '';
        };

        const fillClientFields = (client) => {
            clientSelect.value = client.id;
            clientFirstName.value = client.first_name || '';
            clientLastName.value = client.last_name || '';
            clientPhone.value = client.phone || '';
            clientEmail.value = client.email || '';
            clientLookup.value = client.label || client.first_name || '';
            clientLookupHint.textContent = 'Cliente existente seleccionado. Sus datos fueron cargados.';
            closeClientResults();
        };

        const renderClientResults = (clients) => {
            clientResults.innerHTML = '';

            if (!clients.length) {
                clientLookupHint.textContent = 'No encontre clientes con ese dato. Puedes crear uno nuevo.';
                closeClientResults();
                return;
            }

            clients.forEach((client) => {
                const button = document.createElement('button');
                const name = document.createElement('b');
                const details = document.createElement('span');

                button.type = 'button';
                button.className = 'client-result';
                name.textContent = client.label || 'Cliente sin nombre';
                details.textContent = [client.phone, client.email].filter(Boolean).join(' - ') || 'Sin telefono';
                button.append(name, details);
                button.addEventListener('click', () => fillClientFields(client));
                clientResults.appendChild(button);
            });

            clientResults.classList.add('is-open');
            clientLookupHint.textContent = `${clients.length} cliente${clients.length === 1 ? '' : 's'} encontrado${clients.length === 1 ? '' : 's'}.`;
        };

        const searchClients = async () => {
            const query = clientLookup.value.trim();

            if (query.length < 2) {
                clientLookupHint.textContent = 'Escribe al menos 2 caracteres para buscar clientes existentes.';
                closeClientResults();
                return;
            }

            try {
                const response = await fetch(`/agenda/clientes/buscar?q=${encodeURIComponent(query)}`, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudo buscar clientes.');
                }

                renderClientResults(await response.json());
            } catch (error) {
                clientLookupHint.textContent = error.message || 'No se pudo buscar clientes.';
                closeClientResults();
            }
        };

        clientLookup?.addEventListener('input', () => {
            clientSelect.value = '';
            clearTimeout(clientLookupTimer);
            clientLookupTimer = setTimeout(searchClients, 250);
        });

        [clientFirstName, clientLastName, clientPhone, clientEmail].forEach((input) => {
            input?.addEventListener('input', () => {
                clientSelect.value = '';
                clientLookupHint.textContent = 'Se guardara como cliente nuevo al crear la cita.';
            });
        });

        clientSelect?.addEventListener('change', () => {
            if (!clientSelect.value) {
                clientLookup.value = '';
                clientLookupHint.textContent = 'Si el cliente existe, seleccionalo para cargar sus datos.';
                return;
            }

            const option = clientSelect.options[clientSelect.selectedIndex];
            clientLookup.value = option.textContent.trim();
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.client-lookup')) {
                closeClientResults();
            }
        });
    </script>
@endsection
