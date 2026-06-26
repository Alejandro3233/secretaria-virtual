@extends('layouts.app')

@section('title', 'Personal - Secretaria Virtual')
@section('page_title', 'Personal')
@section('page_subtitle', 'Administra estilistas, servicios y horarios de trabajo.')
@section('page_actions')
    <a class="btn" href="/agenda">Ver agenda</a>
@endsection

@section('content')
    <style>
        .workday-check {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
            font-size: 13px;
            font-weight: 800;
        }
        input[type="checkbox"] {
            accent-color: #1d4ed8;
            outline: 0;
            box-shadow: none;
        }
        .vacation-panel {
            display: grid;
            gap: 8px;
            min-width: 260px;
        }
        .vacation-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
        }
        .vacation-form input {
            min-height: 34px;
            padding: 6px 8px;
            font-size: 12px;
        }
        .vacation-form .vacation-reason {
            grid-column: 1 / -1;
        }
        .vacation-form .btn {
            grid-column: 1 / -1;
            min-height: 32px;
            padding: 0 10px;
            font-size: 12px;
        }
        .vacation-list {
            display: grid;
            gap: 6px;
            margin-top: 2px;
        }
        .vacation-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
            padding: 7px 9px;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            background: #f0fdf4;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
        }
        .vacation-item small {
            display: block;
            margin-top: 2px;
            color: #15803d;
            font-weight: 700;
        }
        .vacation-item button {
            min-height: 26px;
            padding: 0 8px;
            border-color: #bbf7d0;
            color: #166534;
            font-size: 11px;
        }
        .vacation-empty {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }
    </style>
    @if (session('staff_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('staff_status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            Revisa los campos marcados antes de guardar.
        </div>
    @endif

    <form method="POST" action="/personal" class="card" style="margin-bottom:18px;">
        @csrf

        <div class="section-title">
            <h2>Agregar estilista</h2>
            <button class="btn primary" type="submit">Guardar personal</button>
        </div>

        <section class="grid-3">
            <div>
                <label for="name">Nombre</label>
                <input id="name" name="name" value="{{ old('name') }}" placeholder="Sofia Herrera" required>
                @error('name') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="service_id">Servicio principal</label>
                <select id="service_id" name="service_id">
                    <option value="">Sin servicio asignado</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
                @error('service_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="specialty">Especialidad</label>
                <input id="specialty" name="specialty" value="{{ old('specialty') }}" placeholder="Color, unas, cortes">
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="phone">Telefono</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" placeholder="+1 555 0100">
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="estilista@email.com">
                @error('email') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
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

        <section class="grid-3" style="margin-top:18px;">
            <div style="grid-column:span 2;">
                <label>Dias de trabajo</label>
                <div class="actions" style="gap:8px;">
                    @foreach ($workDays as $dayKey => $dayLabel)
                        <label class="workday-check">
                            <input type="checkbox" name="work_days[]" value="{{ $dayKey }}" @checked(in_array($dayKey, old('work_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']), true)) style="width:auto;min-height:auto;margin-right:6px;">
                            {{ $dayLabel }}
                        </label>
                    @endforeach
                </div>
                @error('work_days') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div class="grid-2">
                <div>
                    <label for="work_starts_at">Entrada</label>
                    <input id="work_starts_at" name="work_starts_at" type="time" value="{{ old('work_starts_at', '08:00') }}">
                </div>
                <div>
                    <label for="work_ends_at">Salida</label>
                    <input id="work_ends_at" name="work_ends_at" type="time" value="{{ old('work_ends_at', '21:00') }}">
                </div>
            </div>
        </section>
    </form>

    <article class="card">
        <div class="section-title">
            <h2>Equipo registrado</h2>
            <span class="subtitle">{{ $stylists->count() }} personas</span>
        </div>

        @if ($stylists->isEmpty())
            <span class="subtitle">Todavia no hay personal agregado para este salon.</span>
        @else
            @foreach ($stylists as $stylist)
                <form id="stylist-form-{{ $stylist->id }}" method="POST" action="/personal/{{ $stylist->id }}">
                    @csrf
                    @method('PUT')
                </form>
                <form id="stylist-delete-form-{{ $stylist->id }}" method="POST" action="/personal/{{ $stylist->id }}" onsubmit="return confirm('Eliminar este empleado? Sus citas quedaran sin asignar y su horario dejara de aparecer en la agenda.');">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Servicio</th>
                        <th>Horario</th>
                        <th>Vacaciones</th>
                        <th>Contacto</th>
                        <th>Estado</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stylists as $stylist)
                        <tr>
                            <td>
                                <input form="stylist-form-{{ $stylist->id }}" name="name" value="{{ old('name', $stylist->name) }}" required>
                                @if($stylist->is_internal && ! $clinic->google_connected_at)
                                    <span class="status wait" style="display:inline-block;margin-top:8px;">Google desconectado</span>
                                @endif
                                <input form="stylist-form-{{ $stylist->id }}" name="specialty" value="{{ old('specialty', $stylist->specialty) }}" placeholder="Especialidad" style="margin-top:8px;">
                            </td>
                            <td>
                                <select form="stylist-form-{{ $stylist->id }}" name="service_id">
                                    <option value="">Sin servicio asignado</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}" @selected($stylist->service_id == $service->id)>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="actions" style="gap:6px;margin-bottom:8px;">
                                    @foreach ($workDays as $dayKey => $dayLabel)
                                        <label class="workday-check">
                                            <input form="stylist-form-{{ $stylist->id }}" type="checkbox" name="work_days[]" value="{{ $dayKey }}" @checked(in_array($dayKey, $stylist->work_days ?? [], true)) style="width:auto;min-height:auto;margin-right:6px;">
                                            {{ substr($dayLabel, 0, 3) }}
                                        </label>
                                    @endforeach
                                </div>
                                <div class="grid-2">
                                    <input form="stylist-form-{{ $stylist->id }}" name="work_starts_at" type="time" value="{{ $stylist->work_starts_at ? substr($stylist->work_starts_at, 0, 5) : '' }}">
                                    <input form="stylist-form-{{ $stylist->id }}" name="work_ends_at" type="time" value="{{ $stylist->work_ends_at ? substr($stylist->work_ends_at, 0, 5) : '' }}">
                                </div>
                            </td>
                            <td>
                                <div class="vacation-panel">
                                    <form method="POST" action="/personal/{{ $stylist->id }}/vacaciones" class="vacation-form">
                                        @csrf
                                        <input name="starts_on" type="date" value="{{ old('starts_on') }}" aria-label="Inicio de vacaciones" required>
                                        <input name="ends_on" type="date" value="{{ old('ends_on') }}" aria-label="Fin de vacaciones" required>
                                        <input class="vacation-reason" name="reason" value="{{ old('reason') }}" placeholder="Motivo opcional">
                                        <button class="btn" type="submit">Asignar vacaciones</button>
                                    </form>

                                    @if ($stylist->vacations->isEmpty())
                                        <span class="vacation-empty">Sin vacaciones próximas.</span>
                                    @else
                                        <div class="vacation-list">
                                            @foreach ($stylist->vacations as $vacation)
                                                <div class="vacation-item">
                                                    <span>
                                                        {{ $vacation->starts_on->format('d/m/Y') }} - {{ $vacation->ends_on->format('d/m/Y') }}
                                                        @if ($vacation->reason)
                                                            <small>{{ $vacation->reason }}</small>
                                                        @endif
                                                    </span>
                                                    <form method="POST" action="/personal/{{ $stylist->id }}/vacaciones/{{ $vacation->id }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn" type="submit">Quitar</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <input form="stylist-form-{{ $stylist->id }}" name="phone" value="{{ old('phone', $stylist->phone) }}" placeholder="Telefono">
                                <input form="stylist-form-{{ $stylist->id }}" name="email" type="email" value="{{ old('email', $stylist->email) }}" placeholder="Email" style="margin-top:8px;">
                            </td>
                            <td>
                                <input form="stylist-form-{{ $stylist->id }}" type="hidden" name="is_active" value="0">
                                <label style="display:flex;align-items:center;gap:10px;margin:0;color:var(--ink);">
                                    <input form="stylist-form-{{ $stylist->id }}" type="checkbox" name="is_active" value="1" @checked($stylist->is_active) style="width:auto;min-height:auto;">
                                    Activo
                                </label>
                            </td>
                            <td>
                                <div class="actions" style="gap:8px;">
                                    <button form="stylist-form-{{ $stylist->id }}" class="btn primary" type="submit">Actualizar</button>
                                    <button form="stylist-delete-form-{{ $stylist->id }}" class="btn" type="submit">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </article>
@endsection
