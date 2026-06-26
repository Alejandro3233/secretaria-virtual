@extends('layouts.app')

@section('title', 'Gestion de usuarios - Secretaria Virtual')
@section('page_title', 'Gestion de usuarios')
@section('page_subtitle', 'Administra accesos de usuarios activos y deshabilitados.')
@section('page_actions')
    <a class="btn" href="/base-de-datos/users">Ver tabla de usuarios</a>
@endsection

@section('content')
    <style>
        .user-tabs { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:18px; }
        .user-tabs a { border:1px solid var(--line); border-radius:999px; padding:8px 13px; background:#fff; color:var(--muted); font-size:12px; font-weight:900; }
        .user-tabs a.active { border-color:var(--brand); background:var(--brand); color:#fff; }
        .user-actions { display:flex; gap:6px; flex-wrap:wrap; min-width:210px; }
        .user-actions form { margin:0; }
        .user-actions .btn { min-height:32px; padding:0 10px; font-size:11px; }
        .btn.user-danger { border-color:#fecaca; background:#fff1f2; color:#b91c1c; }
        .btn.user-enable { border-color:#bbf7d0; background:#f0fdf4; color:#166534; }
        .user-table .status { display:inline; border:0; border-radius:0; padding:0; background:transparent !important; box-shadow:none; }
    </style>

    @if (session('user_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('user_status') }}
        </div>
    @endif

    @if (session('user_error'))
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('user_error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            Revisa los campos marcados antes de crear el usuario.
        </div>
    @endif

    <form method="POST" action="/gestion-usuarios" class="card" style="margin-bottom:18px;">
        @csrf

        <div class="section-title">
            <div>
                <h2>Nuevo usuario</h2>
                <span class="subtitle">La cuenta quedara activa y podra iniciar sesion inmediatamente.</span>
            </div>
            <button class="btn primary" type="submit">Crear usuario</button>
        </div>

        <section class="grid-3">
            <div>
                <label for="name">Nombre</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
                @error('name') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="last_name">Apellidos</label>
                <input id="last_name" name="last_name" value="{{ old('last_name') }}">
                @error('last_name') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="mobile_phone">Telefono movil</label>
                <input id="mobile_phone" name="mobile_phone" value="{{ old('mobile_phone') }}" placeholder="+34 600 000 000">
                @error('mobile_phone') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="email">Correo electronico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                @error('email') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="password">Contrasena</label>
                <input id="password" name="password" type="password" minlength="8" required>
                @error('password') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="password_confirmation">Confirmar contrasena</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required>
            </div>
        </section>

        <section class="grid-3" style="margin-top:18px;">
            <div>
                <label for="clinic_id">Clinica asignada</label>
                <select id="clinic_id" name="clinic_id" required>
                    <option value="">Seleccionar clinica</option>
                    @foreach ($clinics as $clinic)
                        <option value="{{ $clinic->id }}" @selected(old('clinic_id') == $clinic->id)>{{ $clinic->name }}</option>
                    @endforeach
                </select>
                @error('clinic_id') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="role">Rol en la clinica</label>
                <select id="role" name="role" required>
                    <option value="staff" @selected(old('role', 'staff') === 'staff')>Personal</option>
                    <option value="owner" @selected(old('role') === 'owner')>Propietario</option>
                </select>
                @error('role') <div class="danger" style="margin-top:8px;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label>Permisos globales</label>
                <label style="display:flex;align-items:center;gap:10px;min-height:42px;margin:0;color:var(--ink);">
                    <input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin')) style="width:auto;min-height:auto;">
                    Convertir en super admin
                </label>
            </div>
        </section>
    </form>

    <nav class="user-tabs" aria-label="Estados de usuarios">
        <a class="{{ $section === 'activos' ? 'active' : '' }}" href="{{ route('users.index', ['estado' => 'activos']) }}">Activos ({{ $userCounts['activos'] }})</a>
        <a class="{{ $section === 'deshabilitados' ? 'active' : '' }}" href="{{ route('users.index', ['estado' => 'deshabilitados']) }}">Deshabilitados ({{ $userCounts['deshabilitados'] }})</a>
    </nav>

    <article class="card" style="overflow:auto;">
        <div class="section-title">
            <h2>{{ $section === 'deshabilitados' ? 'Usuarios deshabilitados' : 'Usuarios activos' }}</h2>
            <span class="subtitle">{{ $users->count() }} usuarios</span>
        </div>

        <table class="user-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Contacto</th>
                    <th>Clinica y rol</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td><b>{{ trim($user->name.' '.$user->last_name) }}</b></td>
                        <td>{{ $user->email }}<br><span class="subtitle">{{ $user->mobile_phone ?: 'Sin telefono' }}</span></td>
                        <td>
                            @forelse ($user->clinics as $clinic)
                                <div>{{ $clinic->name }} <span class="subtitle">({{ $clinic->pivot->role === 'owner' ? 'Propietario' : 'Personal' }})</span></div>
                            @empty
                                <span class="subtitle">Sin clinica asignada</span>
                            @endforelse
                        </td>
                        <td><span class="status {{ $user->is_super_admin ? 'wait' : 'info' }}">{{ $user->is_super_admin ? 'Super admin' : 'Usuario' }}</span></td>
                        <td>
                            @if($user->is_active)
                                <span class="status ok">Activo</span>
                            @else
                                <span class="status wait">Deshabilitado</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="user-actions">
                                @if(auth()->id() === $user->id)
                                    <span class="subtitle">Tu cuenta</span>
                                @else
                                    <form method="POST" action="{{ route('users.status', $user) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                        <button class="btn {{ $user->is_active ? '' : 'user-enable' }}" type="submit">{{ $user->is_active ? 'Deshabilitar' : 'Habilitar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('El usuario se eliminará definitivamente y no se podrá restaurar. ¿Continuar?');">
                                        @csrf @method('DELETE')
                                        <button class="btn user-danger" type="submit">Eliminar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay usuarios en esta sección.</td></tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
