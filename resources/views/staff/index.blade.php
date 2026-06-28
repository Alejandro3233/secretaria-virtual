@extends('layouts.app')

@section('title', 'Personal - Secretary365')
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
        .service-dropdown { position:relative; }
        .service-dropdown summary { min-height:36px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 10px;border:1px solid #dbcbd4;border-radius:6px;background:#fff;color:var(--ink);font-size:13px;font-weight:800;cursor:pointer;list-style:none; }
        .service-dropdown summary::-webkit-details-marker { display:none; }
        .service-dropdown summary::after { content:'▾';color:var(--muted); }
        .service-dropdown[open] summary::after { transform:rotate(180deg); }
        .service-checks { position:absolute;z-index:30;top:calc(100% + 5px);left:0;right:0;display:grid;gap:6px;max-height:210px;overflow:auto;padding:9px;border:1px solid var(--line);border-radius:6px;background:#fff;box-shadow:0 12px 28px rgba(24,18,22,.14); }
        .service-check { display:flex;align-items:center;gap:7px;margin:0;color:var(--ink);font-size:12px;font-weight:800; }
        .service-check input { width:15px;height:15px;min-height:0;flex:0 0 15px; }
        .service-check-all { padding-bottom:7px;border-bottom:1px solid var(--line);color:var(--brand); }
        .staff-card-table,.staff-card-table tbody,.staff-card-table tr,.staff-card-table td { display:block; }
        .staff-card-table { width:100%; }
        .staff-card-table thead { display:none; }
        .staff-card-table tbody { display:grid;gap:14px; }
        .staff-card-table tr { display:grid;grid-template-columns:minmax(180px,.8fr) minmax(200px,1fr) minmax(250px,1.2fr) minmax(240px,1.1fr);gap:18px;align-items:start;padding:18px;border:1px solid var(--line);border-left:5px solid var(--brand);border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(24,18,22,.05);transition:transform .16s ease,box-shadow .16s ease; }
        .staff-card-table tr:hover { transform:translateY(-1px);box-shadow:0 8px 22px rgba(24,18,22,.09); }
        .staff-card-table td { min-width:0;padding:0;border:0; }
        .staff-card-table td::before { display:block;margin-bottom:7px;color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.04em; }
        .staff-card-table td:nth-child(1)::before { content:'Empleado'; }
        .staff-card-table td:nth-child(2)::before { content:'Servicios'; }
        .staff-card-table td:nth-child(3)::before { content:'Horario y descanso'; }
        .staff-card-table td:nth-child(4)::before { content:'Vacaciones'; }
        .staff-card-table td:nth-child(5)::before { content:'Contacto'; }
        .staff-card-table td:nth-child(5) { grid-column:span 2;display:grid;grid-template-columns:1fr 1fr;gap:8px; }
        .staff-card-table td:nth-child(5)::before { grid-column:1/-1;margin-bottom:0; }
        .staff-card-table td:nth-child(5) input { margin-top:0!important; }
        .staff-card-table td:nth-child(6)::before { content:'Estado'; }
        .staff-card-table td:nth-child(7)::before { content:'Acciones'; }
        @media(max-width:1250px){.staff-card-table tr{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.staff-card-table tr{grid-template-columns:1fr}.staff-card-table td:nth-child(5){grid-column:auto;grid-template-columns:1fr}.staff-card-table td:nth-child(7) .btn{flex:1}}
        .weekly-schedule { margin-top:12px;border:1px solid var(--line);border-radius:8px;background:#fff; }
        .weekly-schedule>summary { padding:10px 12px;color:var(--brand);font-size:13px;font-weight:900;cursor:pointer; }
        .weekly-schedule-list { display:grid;gap:7px;padding:0 12px 12px; }
        .weekly-schedule-row { display:grid;grid-template-columns:105px repeat(4,minmax(90px,1fr));gap:7px;align-items:end;padding-top:7px;border-top:1px solid #f2eaf0; }
        .weekly-schedule-row label { margin:0;font-size:11px; }
        .weekly-schedule-row input[type="time"] { min-height:32px;padding:0 6px;font-size:12px; }
        .weekly-day-toggle { display:flex;align-items:center;gap:6px!important;color:var(--ink)!important;font-size:12px!important; }
        .weekly-day-toggle input { width:15px!important;height:15px;min-height:0!important; }
        .staff-card-table .weekly-schedule-row { grid-template-columns:1fr 1fr; }
        .staff-card-table .weekly-day-toggle { grid-column:1/-1; }
        .legacy-schedule-fields,.staff-card-table td:nth-child(3)>.actions,.staff-card-table td:nth-child(3)>.grid-2,.staff-card-table td:nth-child(3)>small { display:none!important; }
        @media(max-width:700px){.weekly-schedule-row{grid-template-columns:1fr 1fr}.weekly-day-toggle{grid-column:1/-1}}
        .staff-avatar { width:64px;height:64px;display:grid;place-items:center;overflow:hidden;border-radius:50%;background:#f3e8ee;color:var(--brand);font-size:20px;font-weight:900; }
        .staff-avatar img { width:100%;height:100%;object-fit:cover; }
        .staff-avatar-field { display:flex;align-items:center;gap:12px; }
        .avatar-picker { position:relative; }
        .avatar-picker>summary { display:inline-grid;cursor:pointer;list-style:none; }
        .avatar-picker>summary::-webkit-details-marker { display:none; }
        .avatar-picker>summary:hover .staff-avatar { box-shadow:0 0 0 3px #f9a8d4; }
        .avatar-picker-panel { position:absolute;z-index:50;top:72px;left:0;width:238px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:10px;border:1px solid var(--line);border-radius:10px;background:#fff;box-shadow:0 16px 36px rgba(24,18,22,.18); }
        .preset-avatar { width:64px;height:64px;padding:0;overflow:hidden;border:2px solid transparent;border-radius:50%;background:#f8fafc;cursor:pointer; }
        .preset-avatar:hover { border-color:var(--brand); }
        .preset-avatar img { width:100%;height:100%;object-fit:cover; }
        .staff-avatar-upload { grid-column:1/-1;min-height:34px;display:flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:6px;color:var(--brand);font-size:12px;font-weight:900;cursor:pointer; }
        .staff-avatar-upload input[type="file"] { position:absolute;width:1px;height:1px;opacity:0;pointer-events:none; }
        .staff-avatar-hint { color:var(--muted);font-size:11px;font-weight:800; }
    </style>
    @if (session('staff_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('staff_status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            <div>Revisa los campos marcados antes de guardar:</div>
            <ul style="margin:8px 0 0;padding-left:20px;font-weight:600;line-height:1.5;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="/personal" enctype="multipart/form-data" class="card" style="margin-bottom:18px;">
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
                <label>Servicios que puede realizar</label>
                <details class="service-dropdown" data-service-dropdown>
                    <summary data-service-summary>Seleccionar servicios</summary>
                    <div class="service-checks">
                        <label class="service-check service-check-all"><input type="checkbox" data-select-all> Seleccionar todos</label>
                        @foreach ($services as $service)
                            <label class="service-check"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" data-service-option @checked(in_array($service->id, old('service_ids', [])))> {{ $service->name }}</label>
                        @endforeach
                    </div>
                </details>
                @error('service_ids') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
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

        <section class="grid-3 legacy-schedule-fields" style="margin-top:18px;">
            <div>
                <label for="break_starts_at">Inicio del descanso</label>
                <input id="break_starts_at" name="break_starts_at" type="time" value="{{ old('break_starts_at') }}">
            </div>
            <div>
                <label for="break_ends_at">Fin del descanso</label>
                <input id="break_ends_at" name="break_ends_at" type="time" value="{{ old('break_ends_at') }}">
            </div>
            <div class="subtitle" style="align-self:end;padding-bottom:9px;">Opcional. Este horario quedará bloqueado cada día de trabajo.</div>
        </section>
        @error('break_starts_at') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
        @error('break_ends_at') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror

        <section class="grid-3 legacy-schedule-fields" style="margin-top:18px;">
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
        <details class="weekly-schedule">
            <summary>Configurar un horario diferente por día</summary>
            <div class="weekly-schedule-list">
                @foreach ($workDays as $dayKey => $dayLabel)
                    @php($daySchedule = old("weekly_schedule.$dayKey", []))
                    <div class="weekly-schedule-row">
                        <label class="weekly-day-toggle"><input type="hidden" name="weekly_schedule[{{ $dayKey }}][enabled]" value="0"><input type="checkbox" name="weekly_schedule[{{ $dayKey }}][enabled]" value="1" @checked($daySchedule ? !empty($daySchedule['enabled']) : in_array($dayKey, ['monday','tuesday','wednesday','thursday','friday'], true))> {{ $dayLabel }}</label>
                        <label>Entrada<input type="time" name="weekly_schedule[{{ $dayKey }}][start]" value="{{ $daySchedule['start'] ?? '09:00' }}"></label>
                        <label>Salida<input type="time" name="weekly_schedule[{{ $dayKey }}][end]" value="{{ $daySchedule['end'] ?? ($dayKey === 'saturday' ? '15:00' : '20:00') }}"></label>
                        <label>Descanso<input type="time" name="weekly_schedule[{{ $dayKey }}][break_start]" value="{{ $daySchedule['break_start'] ?? '' }}"></label>
                        <label>Fin descanso<input type="time" name="weekly_schedule[{{ $dayKey }}][break_end]" value="{{ $daySchedule['break_end'] ?? '' }}"></label>
                    </div>
                @endforeach
            </div>
        </details>
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
                <form id="stylist-form-{{ $stylist->id }}" method="POST" action="/personal/{{ $stylist->id }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                </form>
                <form id="stylist-delete-form-{{ $stylist->id }}" method="POST" action="/personal/{{ $stylist->id }}" onsubmit="return confirm('Eliminar este empleado? Sus citas quedaran sin asignar y su horario dejara de aparecer en la agenda.');">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            <table class="staff-card-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Servicios</th>
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
                                <div class="staff-avatar-field" style="margin-bottom:10px;">
                                    <details class="avatar-picker">
                                        <summary title="Cambiar avatar de {{ $stylist->name }}"><span class="staff-avatar" data-avatar-preview>@if($stylist->avatarUrl())<img src="{{ $stylist->avatarUrl() }}" alt="Foto de {{ $stylist->name }}">@else{{ $stylist->initials() }}@endif</span></summary>
                                        <div class="avatar-picker-panel">
                                            @foreach(['avatar-man-1.jpg','avatar-man-2.jpg','avatar-man-3.jpg','avatar-woman-1.jpg','avatar-woman-2.jpg','avatar-woman-3.jpg'] as $preset)
                                                <button class="preset-avatar" type="button" data-preset-avatar="{{ $preset }}"><img src="{{ asset('images/staff-avatars/'.$preset) }}" alt="Avatar animado"></button>
                                            @endforeach
                                            <label class="staff-avatar-upload">Subir mi foto<input form="stylist-form-{{ $stylist->id }}" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Subir foto de {{ $stylist->name }}" data-avatar-input></label>
                                            <input form="stylist-form-{{ $stylist->id }}" type="hidden" name="preset_avatar" value="" data-preset-avatar-input>
                                        </div>
                                    </details>
                                    <span class="staff-avatar-hint">Toca el avatar para cambiar la foto</span>
                                </div>
                                <input form="stylist-form-{{ $stylist->id }}" name="name" value="{{ old('name', $stylist->name) }}" required>
                                @if($stylist->is_internal && ! $clinic->google_connected_at)
                                    <span class="status wait" style="display:inline-block;margin-top:8px;">Google desconectado</span>
                                @endif
                                <input form="stylist-form-{{ $stylist->id }}" name="specialty" value="{{ old('specialty', $stylist->specialty) }}" placeholder="Especialidad" style="margin-top:8px;">
                            </td>
                            <td>
                                <details class="service-dropdown" data-service-dropdown>
                                    <summary data-service-summary>Seleccionar servicios</summary>
                                    <div class="service-checks">
                                        <label class="service-check service-check-all"><input type="checkbox" data-select-all> Seleccionar todos</label>
                                        @foreach ($services as $service)
                                            <label class="service-check"><input form="stylist-form-{{ $stylist->id }}" type="checkbox" name="service_ids[]" value="{{ $service->id }}" data-service-option @checked($stylist->services->contains('id', $service->id) || $stylist->service_id == $service->id)> {{ $service->name }}</label>
                                        @endforeach
                                    </div>
                                </details>
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
                                <small class="subtitle" style="display:block;margin:8px 0 5px;font-weight:800;">Descanso diario</small>
                                <div class="grid-2">
                                    <input form="stylist-form-{{ $stylist->id }}" name="break_starts_at" type="time" value="{{ $stylist->break_starts_at ? substr($stylist->break_starts_at, 0, 5) : '' }}" aria-label="Inicio del descanso">
                                    <input form="stylist-form-{{ $stylist->id }}" name="break_ends_at" type="time" value="{{ $stylist->break_ends_at ? substr($stylist->break_ends_at, 0, 5) : '' }}" aria-label="Fin del descanso">
                                </div>
                                <details class="weekly-schedule">
                                    <summary>Horario por día</summary>
                                    <div class="weekly-schedule-list">
                                        @foreach ($workDays as $dayKey => $dayLabel)
                                            @php($savedDay = $stylist->weekly_schedule[$dayKey] ?? null)
                                            <div class="weekly-schedule-row">
                                                <label class="weekly-day-toggle"><input form="stylist-form-{{ $stylist->id }}" type="hidden" name="weekly_schedule[{{ $dayKey }}][enabled]" value="0"><input form="stylist-form-{{ $stylist->id }}" type="checkbox" name="weekly_schedule[{{ $dayKey }}][enabled]" value="1" @checked($savedDay ? !empty($savedDay['enabled']) : in_array($dayKey, $stylist->work_days ?? [], true))> {{ $dayLabel }}</label>
                                                <label>Entrada<input form="stylist-form-{{ $stylist->id }}" type="time" name="weekly_schedule[{{ $dayKey }}][start]" value="{{ $savedDay['start'] ?? ($stylist->work_starts_at ? substr($stylist->work_starts_at,0,5) : '09:00') }}"></label>
                                                <label>Salida<input form="stylist-form-{{ $stylist->id }}" type="time" name="weekly_schedule[{{ $dayKey }}][end]" value="{{ $savedDay['end'] ?? ($stylist->work_ends_at ? substr($stylist->work_ends_at,0,5) : '20:00') }}"></label>
                                                <label>Descanso<input form="stylist-form-{{ $stylist->id }}" type="time" name="weekly_schedule[{{ $dayKey }}][break_start]" value="{{ $savedDay['break_start'] ?? ($stylist->break_starts_at ? substr($stylist->break_starts_at,0,5) : '') }}"></label>
                                                <label>Fin<input form="stylist-form-{{ $stylist->id }}" type="time" name="weekly_schedule[{{ $dayKey }}][break_end]" value="{{ $savedDay['break_end'] ?? ($stylist->break_ends_at ? substr($stylist->break_ends_at,0,5) : '') }}"></label>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
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
    <script>
        document.querySelectorAll('[data-service-dropdown]').forEach((dropdown) => {
            const options = [...dropdown.querySelectorAll('[data-service-option]')];
            const selectAll = dropdown.querySelector('[data-select-all]');
            const summary = dropdown.querySelector('[data-service-summary]');
            const update = () => {
                const selected = options.filter((option) => option.checked);
                selectAll.checked = options.length > 0 && selected.length === options.length;
                selectAll.indeterminate = selected.length > 0 && selected.length < options.length;
                summary.textContent = selected.length === 0 ? 'Seleccionar servicios' : selected.length === 1 ? selected[0].parentElement.textContent.trim() : `${selected.length} servicios seleccionados`;
            };
            selectAll.addEventListener('change', () => {
                options.forEach((option) => option.checked = selectAll.checked);
                update();
            });
            options.forEach((option) => option.addEventListener('change', update));
            update();
        });
        document.querySelectorAll('[data-avatar-input]').forEach((input) => input.addEventListener('change', () => {
            const file = input.files?.[0];
            const picker = input.closest('.avatar-picker');
            const preview = picker?.querySelector('[data-avatar-preview]');
            if (!file || !preview) return;
            picker.querySelector('[data-preset-avatar-input]').value = '';
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = 'Vista previa de la nueva foto';
            image.onload = () => URL.revokeObjectURL(image.src);
            preview.replaceChildren(image);
            picker.removeAttribute('open');
        }));
        document.querySelectorAll('[data-preset-avatar]').forEach((button) => button.addEventListener('click', () => {
            const picker = button.closest('.avatar-picker');
            picker.querySelector('[data-preset-avatar-input]').value = button.dataset.presetAvatar;
            picker.querySelector('[data-avatar-input]').value = '';
            const image = button.querySelector('img').cloneNode();
            image.alt = 'Vista previa del avatar seleccionado';
            picker.querySelector('[data-avatar-preview]').replaceChildren(image);
            picker.removeAttribute('open');
        }));
    </script>
@endsection
