@extends('layouts.app')

@section('title', 'Base de datos - Secretaria Virtual')
@section('page_title', 'Base de datos')
@section('page_subtitle', 'Consulta, crea, edita y elimina registros del sistema.')
@section('page_actions')
    <a class="btn" href="/consola">Volver a consola</a>
@endsection

@section('content')
    <style>
        .database-shell { display:grid; grid-template-columns:280px minmax(0,1fr); gap:14px; align-items:start; }
        .database-tables { display:grid; gap:8px; max-height:calc(100vh - 190px); overflow:auto; }
        .database-table-link { display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid var(--line); border-radius:8px; padding:10px 11px; color:var(--ink); font-weight:800; }
        .database-table-link span:last-child { border-radius:999px; padding:3px 8px; background:#f4edf1; color:var(--muted); font-size:11px; }
        .database-table-link.active { border-color:var(--brand); background:#fff4f7; color:var(--brand); }
        .database-toolbar { display:grid; grid-template-columns:minmax(240px,1fr) auto; gap:10px; align-items:end; margin-bottom:14px; }
        .database-search { display:grid; gap:6px; }
        .database-bulkbar { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; border:1px solid var(--line); border-radius:8px; padding:10px 12px; background:#fffafd; }
        .database-bulkbar span { color:var(--muted); font-size:12px; font-weight:800; }
        .database-select-cell { width:42px !important; min-width:42px; text-align:center; }
        .database-select-cell input { width:auto; min-width:0; }
        .database-insert { margin-bottom:14px; border:1px solid var(--line); border-radius:8px; padding:14px; background:#fffafd; }
        .database-insert-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; }
        .database-field { display:grid; gap:5px; }
        .database-field label { color:var(--muted); font-size:11px; font-weight:900; text-transform:uppercase; }
        .database-field textarea { min-height:76px; resize:vertical; }
        .database-scroll { overflow:auto; border:1px solid var(--line); border-radius:8px; }
        .database-table { width:max-content; min-width:100%; table-layout:auto; border-collapse:collapse; }
        .database-table th { position:sticky; top:0; z-index:1; background:#fff7fb; color:var(--muted); font-size:11px; text-transform:uppercase; }
        .database-table th, .database-table td { border-bottom:1px solid #f2eaf0; padding:9px; vertical-align:top; white-space:nowrap; }
        .database-table input, .database-table textarea { width:180px; min-width:180px; }
        .database-table textarea { height:58px; resize:vertical; white-space:pre-wrap; }
        .database-table input[disabled], .database-table textarea[disabled] { background:#f8f5f7; color:#6b5f67; }
        .database-actions { display:grid; gap:7px; min-width:110px; }
        .database-pagination { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:14px; color:var(--muted); font-size:12px; font-weight:800; }
        .database-page-links { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .database-danger { border-color:#fecaca !important; color:#991b1b !important; }
        @media(max-width:980px){ .database-shell{grid-template-columns:1fr}.database-tables{max-height:280px}.database-toolbar{grid-template-columns:1fr} }
    </style>

    @php
        $wideColumns = ['body', 'error', 'metadata', 'transcript', 'notes', 'internal_notes', 'client_comments', 'notification_preferences', 'google_access_token', 'google_refresh_token', 'before', 'after'];
        $fieldTag = fn (string $column) => in_array($column, $wideColumns, true) || str_ends_with($column, '_json') ? 'textarea' : 'input';
    @endphp

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
        <aside class="card">
            <div class="section-title"><h2>Tablas</h2></div>
            <div class="database-tables">
                @foreach ($tables as $tableName)
                    <a class="database-table-link {{ $tableName === $table ? 'active' : '' }}" href="/base-de-datos/{{ $tableName }}">
                        <span>{{ $tableName }}</span>
                        <span>{{ $counts[$tableName] }}</span>
                    </a>
                @endforeach
            </div>
        </aside>

        <main class="card">
            <div class="section-title">
                <div>
                    <h2>{{ $table }}</h2>
                    <span class="subtitle">{{ $isEditable ? 'Edicion habilitada' : 'Solo lectura' }} - {{ $rows->total() }} registro{{ $rows->total() === 1 ? '' : 's' }}</span>
                </div>
            </div>

            <form class="database-toolbar" method="GET" action="/base-de-datos/{{ $table }}">
                <div class="database-search">
                    <label for="database_search">Buscar en esta tabla</label>
                    <input id="database_search" name="q" value="{{ $search }}" placeholder="Nombre, telefono, email, estado...">
                </div>
                <div class="actions" style="margin:0;">
                    <button class="btn primary" type="submit">Buscar</button>
                    @if ($search !== '')
                        <a class="btn" href="/base-de-datos/{{ $table }}">Limpiar</a>
                    @endif
                </div>
            </form>

            @if ($isEditable)
                <details class="database-insert" @if($errors->has('database_insert')) open @endif>
                    <summary style="cursor:pointer;font-weight:900;">Insertar nuevo registro en {{ $table }}</summary>
                    <form method="POST" action="/base-de-datos/{{ $table }}" style="margin-top:12px;">
                        @csrf
                        <div class="database-insert-grid">
                            @foreach ($insertableColumns as $column)
                                <div class="database-field">
                                    <label for="new_{{ $column }}">{{ $column }}</label>
                                    @if ($fieldTag($column) === 'textarea')
                                        <textarea id="new_{{ $column }}" name="{{ $column }}">{{ old($column) }}</textarea>
                                    @else
                                        <input id="new_{{ $column }}" name="{{ $column }}" value="{{ old($column) }}">
                                    @endif
                                </div>
                            @endforeach
                            @if ($table === 'users')
                                <div class="database-field">
                                    <label for="new_password">password</label>
                                    <input id="new_password" name="new_password" type="password" autocomplete="new-password">
                                </div>
                                <div class="database-field">
                                    <label for="new_password_confirmation">confirmar password</label>
                                    <input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password">
                                </div>
                            @endif
                        </div>
                        <div class="actions" style="justify-content:flex-end;margin-top:12px;">
                            <button class="btn primary" type="submit">Crear registro</button>
                        </div>
                    </form>
                </details>
            @endif

            @if ($isEditable)
                <form id="database-bulk-delete" method="POST" action="/base-de-datos/{{ $table }}/eliminar-seleccionados">
                    @csrf
                </form>
                <div class="database-bulkbar">
                    <span><b data-selected-count>0</b> registro(s) seleccionado(s)</span>
                    <button
                        class="btn database-danger"
                        type="submit"
                        form="database-bulk-delete"
                        data-bulk-delete-button
                        disabled
                        onclick="return confirm('Seguro que quieres eliminar los registros seleccionados de {{ $table }}? Esta accion no se puede deshacer.');"
                    >Eliminar seleccionados</button>
                </div>
            @endif

            <div class="database-scroll">
                <table class="database-table">
                    <thead>
                        <tr>
                            @if ($isEditable)
                                <th class="database-select-cell"><input type="checkbox" data-select-all aria-label="Seleccionar todos los registros visibles"></th>
                            @endif
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                            @if ($isEditable && $table === 'users')
                                <th>Nueva contrasena</th>
                                <th>Confirmar</th>
                            @endif
                            @if ($isEditable)
                                <th>Accion</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @if ($isEditable)
                                    <td class="database-select-cell">
                                        <input form="database-bulk-delete" type="checkbox" name="ids[]" value="{{ $row->id }}" data-row-select aria-label="Seleccionar registro {{ $row->id }}">
                                    </td>
                                @endif
                                @if ($isEditable)
                                    <form method="POST" action="/base-de-datos/{{ $table }}/{{ $row->id }}">
                                        @csrf
                                @endif
                                    @foreach ($columns as $column)
                                        @php($value = $row->{$column})
                                        <td>
                                            @if (! $isEditable || in_array($column, $lockedColumns, true))
                                                @if ($fieldTag($column) === 'textarea')
                                                    <textarea disabled>{{ $column === 'password' && $value ? '********' : $value }}</textarea>
                                                @else
                                                    <input value="{{ $column === 'password' && $value ? '********' : $value }}" disabled>
                                                @endif
                                            @else
                                                @if ($fieldTag($column) === 'textarea')
                                                    <textarea name="{{ $column }}">{{ is_bool($value) ? (int) $value : $value }}</textarea>
                                                @else
                                                    <input name="{{ $column }}" value="{{ is_bool($value) ? (int) $value : $value }}">
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                    @if ($isEditable && $table === 'users')
                                        <td><input name="new_password" type="password" placeholder="Sin cambiar"></td>
                                        <td><input name="new_password_confirmation" type="password" placeholder="Repetir"></td>
                                    @endif
                                    @if ($isEditable)
                                        <td>
                                            <div class="database-actions">
                                                <button class="btn primary" type="submit">Guardar</button>
                                                <button
                                                    class="btn database-danger"
                                                    type="submit"
                                                    formaction="/base-de-datos/{{ $table }}/{{ $row->id }}/eliminar"
                                                    formmethod="POST"
                                                    onclick="return confirm('Seguro que quieres eliminar este registro de {{ $table }}? Esta accion no se puede deshacer.');"
                                                >Eliminar</button>
                                            </div>
                                        </td>
                                    @endif
                                @if ($isEditable)
                                    </form>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) + ($isEditable ? ($table === 'users' ? 4 : 2) : 0) }}">No hay registros para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="database-pagination">
                <span>Pagina {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</span>
                <div class="database-page-links">
                    @if ($rows->onFirstPage())
                        <span class="btn">Anterior</span>
                    @else
                        <a class="btn" href="{{ $rows->previousPageUrl() }}">Anterior</a>
                    @endif
                    @if ($rows->hasMorePages())
                        <a class="btn" href="{{ $rows->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="btn">Siguiente</span>
                    @endif
                </div>
            </div>
        </main>
    </section>

    <script>
        const databaseSelectAll = document.querySelector('[data-select-all]');
        const databaseRowSelects = Array.from(document.querySelectorAll('[data-row-select]'));
        const databaseSelectedCount = document.querySelector('[data-selected-count]');
        const databaseBulkDeleteButton = document.querySelector('[data-bulk-delete-button]');

        const updateDatabaseSelection = () => {
            const selected = databaseRowSelects.filter((checkbox) => checkbox.checked).length;
            if (databaseSelectedCount) databaseSelectedCount.textContent = selected;
            if (databaseBulkDeleteButton) databaseBulkDeleteButton.disabled = selected === 0;
            if (databaseSelectAll) {
                databaseSelectAll.checked = selected > 0 && selected === databaseRowSelects.length;
                databaseSelectAll.indeterminate = selected > 0 && selected < databaseRowSelects.length;
            }
        };

        databaseSelectAll?.addEventListener('change', () => {
            databaseRowSelects.forEach((checkbox) => { checkbox.checked = databaseSelectAll.checked; });
            updateDatabaseSelection();
        });
        databaseRowSelects.forEach((checkbox) => checkbox.addEventListener('change', updateDatabaseSelection));
        updateDatabaseSelection();
    </script>
@endsection
