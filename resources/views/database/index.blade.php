@extends('layouts.app')

@section('title', 'Base de datos - Secretaria Virtual')
@section('page_title', 'Base de datos')
@section('page_subtitle', 'Panel exclusivo para super admin. Visualiza y edita registros principales del sistema.')
@section('page_actions')
    <a class="btn" href="/consola">Volver a consola</a>
@endsection

@section('content')
    <style>
        .database-table {
            width: max-content;
            min-width: 100%;
            table-layout: auto;
        }

        .database-table th,
        .database-table td {
            width: 1%;
            white-space: nowrap;
        }

        .database-table input {
            width: auto;
            min-width: 130px;
            max-width: 240px;
        }

        .database-table input[name="email"],
        .database-table input[name="google_id"],
        .database-table input[name="avatar_url"],
        .database-table input[name="new_password"],
        .database-table input[name="new_password_confirmation"] {
            min-width: 190px;
        }

        .database-actions {
            display: grid;
            gap: 8px;
        }

        .database-shell {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            gap: 14px;
            align-items: start;
        }

        .database-tables {
            align-self: start;
        }

        @media (max-width: 900px) {
            .database-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @if (session('database_status'))
        <div class="card" style="margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
            {{ session('database_status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="database-shell">
        <aside class="card database-tables">
            <div class="section-title"><h2>Tablas</h2></div>
            <div style="display:grid;gap:8px;">
                @foreach ($tables as $tableName)
                    <a class="btn {{ $tableName === $table ? 'primary' : '' }}" href="/base-de-datos/{{ $tableName }}" style="justify-content:space-between;">
                        <span>{{ $tableName }}</span>
                        <span>{{ $counts[$tableName] }}</span>
                    </a>
                @endforeach
            </div>
        </aside>

        <section class="card" style="overflow:auto;">
            <div class="section-title">
                <h2>{{ $table }}</h2>
                <span class="subtitle">Mostrando hasta 100 registros</span>
            </div>

            <table class="database-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                        @if ($table === 'users')
                            <th>Nueva contrasena</th>
                            <th>Confirmar contrasena</th>
                        @endif
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <form method="POST" action="/base-de-datos/{{ $table }}/{{ $row->id }}">
                                @csrf
                                @foreach ($columns as $column)
                                    <td>
                                        @php($value = $row->{$column})
                                        @if (in_array($column, $lockedColumns, true))
                                            <input value="{{ $column === 'password' && $value ? '********' : $value }}" disabled>
                                        @else
                                            <input name="{{ $column }}" value="{{ is_bool($value) ? (int) $value : $value }}">
                                        @endif
                                    </td>
                                @endforeach
                                @if ($table === 'users')
                                    <td>
                                        <input name="new_password" type="password" placeholder="Dejar vacio para no cambiar">
                                    </td>
                                    <td>
                                        <input name="new_password_confirmation" type="password" placeholder="Repetir nueva contrasena">
                                    </td>
                                @endif
                                <td>
                                    <div class="database-actions">
                                        <button class="btn primary" type="submit">Guardar</button>
                                    <button
                                        class="btn"
                                        type="submit"
                                        formaction="/base-de-datos/{{ $table }}/{{ $row->id }}/eliminar"
                                        formmethod="POST"
                                        onclick="return confirm('Seguro que quieres eliminar este registro de {{ $table }}? Esta accion no se puede deshacer.');"
                                        style="border-color:#fecaca;color:#991b1b;"
                                    >
                                        Eliminar
                                    </button>
                                    </div>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + ($table === 'users' ? 3 : 1) }}">No hay registros en esta tabla.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </section>
@endsection
