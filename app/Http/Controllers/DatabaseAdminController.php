<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DatabaseAdminController extends Controller
{
    private const EDITABLE_TABLES = [
        'users',
        'subscription_plans',
        'clinics',
        'clinic_users',
        'stylists',
        'clients',
        'client_preferences',
        'services',
        'appointments',
        'appointment_activity_logs',
        'call_logs',
        'notifications',
    ];

    private const LOCKED_COLUMNS = [
        'id',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request, ?string $table = null): View
    {
        $this->authorizeSuperAdmin($request);

        $tables = $this->tables();
        $table = $this->resolveTable($table, $tables);
        $columns = Schema::getColumnListing($table);
        $insertableColumns = $this->editableColumns($table)->values();
        $search = trim((string) $request->query('q', ''));
        $query = DB::table($table);

        if ($search !== '' && $columns !== []) {
            $query->where(function ($inside) use ($columns, $search): void {
                foreach ($columns as $column) {
                    if ($column === 'password') {
                        continue;
                    }

                    $inside->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($columns !== []) {
            $query->orderByDesc(in_array('id', $columns, true) ? 'id' : $columns[0]);
        }

        $rows = $query->paginate(25)->withQueryString();
        $counts = $tables->mapWithKeys(fn (string $name) => [$name => DB::table($name)->count()]);

        return view('database.index', [
            'tables' => $tables,
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'counts' => $counts,
            'lockedColumns' => self::LOCKED_COLUMNS,
            'insertableColumns' => $insertableColumns,
            'isEditable' => in_array($table, self::EDITABLE_TABLES, true),
            'search' => $search,
        ]);
    }

    public function store(Request $request, string $table): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $table = $this->resolveTable($table);
        abort_unless(in_array($table, self::EDITABLE_TABLES, true), 403);

        $columns = $this->editableColumns($table);
        $data = [];

        foreach ($columns as $column) {
            if ($request->has($column)) {
                $data[$column] = $request->input($column) === '' ? null : $request->input($column);
            }
        }

        if ($table === 'users') {
            $request->validate([
                'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            ], [
                'new_password.required' => 'Escribe una contrasena para crear el usuario.',
                'new_password.min' => 'La nueva contrasena debe tener al menos 8 caracteres.',
                'new_password.confirmed' => 'La confirmacion de la nueva contrasena no coincide.',
            ]);

            $data['password'] = Hash::make((string) $request->input('new_password'));
        }

        $now = now();
        $tableColumns = Schema::getColumnListing($table);
        if (in_array('created_at', $tableColumns, true)) {
            $data['created_at'] = $now;
        }
        if (in_array('updated_at', $tableColumns, true)) {
            $data['updated_at'] = $now;
        }

        try {
            DB::table($table)->insert($data);
        } catch (\Throwable $exception) {
            return redirect("/base-de-datos/{$table}")
                ->withErrors(['database_insert' => 'No se pudo insertar el registro: '.$exception->getMessage()])
                ->withInput();
        }

        return redirect("/base-de-datos/{$table}")->with('database_status', 'Registro creado correctamente.');
    }

    public function update(Request $request, string $table, int $id): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $table = $this->resolveTable($table);
        abort_unless(in_array($table, self::EDITABLE_TABLES, true), 403);

        $data = [];

        foreach ($this->editableColumns($table) as $column) {
            if ($request->has($column)) {
                $data[$column] = $request->input($column) === '' ? null : $request->input($column);
            }
        }

        $newPassword = (string) $request->input('new_password', '');

        if ($table === 'users' && $newPassword !== '') {
            $request->validate([
                'new_password' => ['string', 'min:8', 'confirmed'],
            ], [
                'new_password.min' => 'La nueva contrasena debe tener al menos 8 caracteres.',
                'new_password.confirmed' => 'La confirmacion de la nueva contrasena no coincide.',
            ]);

            $data['password'] = Hash::make($newPassword);
        }

        if ($data !== []) {
            DB::table($table)->where('id', $id)->update($data);
        }

        return redirect("/base-de-datos/{$table}")->with('database_status', 'Registro actualizado correctamente.');
    }

    public function destroy(Request $request, string $table, int $id): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $table = $this->resolveTable($table);
        abort_unless(in_array($table, self::EDITABLE_TABLES, true), 403);

        if ($table === 'users') {
            return $this->deleteUsers($request, [$id]);
        }

        if ($table === 'clients') {
            return $this->deleteClients([$id]);
        }

        try {
            $deleted = DB::table($table)->where('id', $id)->delete();
        } catch (\Throwable $exception) {
            return redirect("/base-de-datos/{$table}")
                ->withErrors(['database_delete' => 'No se pudo eliminar el registro. Puede tener datos relacionados en otras tablas.']);
        }

        return redirect("/base-de-datos/{$table}")
            ->with('database_status', $deleted ? 'Registro eliminado correctamente.' : 'El registro ya no existe.');
    }

    public function bulkDestroy(Request $request, string $table): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $table = $this->resolveTable($table);
        abort_unless(in_array($table, self::EDITABLE_TABLES, true), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'Selecciona al menos un registro para eliminar.',
        ]);

        $ids = collect($data['ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return redirect("/base-de-datos/{$table}")->withErrors([
                'database_delete' => 'Selecciona al menos un registro para eliminar.',
            ]);
        }

        if ($table === 'users') {
            return $this->deleteUsers($request, $ids);
        }

        if ($table === 'clients') {
            return $this->deleteClients($ids);
        }

        try {
            $deleted = DB::table($table)->whereIn('id', $ids)->delete();
        } catch (\Throwable $exception) {
            return redirect("/base-de-datos/{$table}")
                ->withErrors(['database_delete' => 'No se pudieron eliminar los registros. Puede haber datos relacionados en otras tablas.']);
        }

        return redirect("/base-de-datos/{$table}")
            ->with('database_status', "{$deleted} registro(s) eliminado(s) correctamente.");
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403);
    }

    private function tables(): \Illuminate\Support\Collection
    {
        return collect(Schema::getTableListing())
            ->map(fn (string $name) => str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name)
            ->reject(fn (string $name) => str_starts_with($name, 'sqlite_'))
            ->unique()
            ->sortBy(fn (string $name) => sprintf(
                '%d-%03d-%s',
                in_array($name, self::EDITABLE_TABLES, true) ? 0 : 1,
                array_search($name, self::EDITABLE_TABLES, true) ?: 0,
                $name,
            ))
            ->values();
    }

    private function resolveTable(?string $table, ?\Illuminate\Support\Collection $tables = null): string
    {
        $tables ??= $this->tables();
        $table = $table ?: $tables->first();

        abort_unless(is_string($table) && $tables->containsStrict($table), 404);

        return $table;
    }

    private function deleteUsers(Request $request, array $ids): RedirectResponse
    {
        $users = User::query()->whereIn('id', $ids)->get();

        if ($users->contains(fn (User $user): bool => $request->user()->is($user))) {
            return redirect('/base-de-datos/users')->withErrors([
                'database_delete' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        $activeSuperAdminIds = User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->pluck('id');
        $deletedSuperAdminIds = $users->where('is_super_admin', true)->pluck('id');

        if ($activeSuperAdminIds->diff($deletedSuperAdminIds)->isEmpty()) {
            return redirect('/base-de-datos/users')->withErrors([
                'database_delete' => 'No puedes eliminar al ultimo superadministrador activo.',
            ]);
        }

        DB::transaction(function () use ($users): void {
            $users->each(function (User $user): void {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->forceDelete();
            });
        });

        return redirect('/gestion-usuarios')->with(
            'user_status',
            $users->count().' usuario(s) eliminado(s) definitivamente.',
        );
    }

    private function deleteClients(array $ids): RedirectResponse
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return redirect('/base-de-datos/clients')->withErrors([
                'database_delete' => 'Selecciona al menos un cliente para eliminar.',
            ]);
        }

        $deleted = 0;

        try {
            DB::transaction(function () use ($ids, &$deleted): void {
                $appointmentIds = DB::table('appointments')
                    ->whereIn('client_id', $ids)
                    ->pluck('id')
                    ->all();

                if ($appointmentIds !== []) {
                    DB::table('appointment_activity_logs')->whereIn('appointment_id', $appointmentIds)->delete();
                    DB::table('notifications')->whereIn('appointment_id', $appointmentIds)->delete();
                    DB::table('call_logs')->whereIn('appointment_id', $appointmentIds)->delete();
                    DB::table('appointments')->whereIn('id', $appointmentIds)->delete();
                }

                DB::table('client_preferences')->whereIn('client_id', $ids)->delete();
                DB::table('notifications')->whereIn('client_id', $ids)->delete();
                DB::table('call_logs')->whereIn('client_id', $ids)->delete();
                $deleted = DB::table('clients')->whereIn('id', $ids)->delete();
            });
        } catch (\Throwable $exception) {
            return redirect('/base-de-datos/clients')->withErrors([
                'database_delete' => 'No se pudieron eliminar los clientes: '.$exception->getMessage(),
            ]);
        }

        return redirect('/base-de-datos/clients')
            ->with('database_status', "{$deleted} cliente(s) eliminado(s) definitivamente.");
    }

    private function editableColumns(string $table): \Illuminate\Support\Collection
    {
        return collect(Schema::getColumnListing($table))
            ->reject(fn (string $column) => in_array($column, self::LOCKED_COLUMNS, true))
            ->values();
    }
}
