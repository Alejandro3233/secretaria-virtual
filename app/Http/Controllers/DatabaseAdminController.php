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
        $query = DB::table($table);

        if ($columns !== []) {
            $query->orderBy(in_array('id', $columns, true) ? 'id' : $columns[0]);
        }

        $rows = $query->limit(100)->get();
        $counts = $tables->mapWithKeys(fn (string $name) => [$name => DB::table($name)->count()]);

        return view('database.index', [
            'tables' => $tables,
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'counts' => $counts,
            'lockedColumns' => self::LOCKED_COLUMNS,
            'isEditable' => in_array($table, self::EDITABLE_TABLES, true),
        ]);
    }

    public function update(Request $request, string $table, int $id): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $table = $this->resolveTable($table);
        abort_unless(in_array($table, self::EDITABLE_TABLES, true), 403);

        $columns = collect(Schema::getColumnListing($table))
            ->reject(fn (string $column) => in_array($column, self::LOCKED_COLUMNS, true));

        $data = [];

        foreach ($columns as $column) {
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
            $user = User::query()->findOrFail($id);

            if ($request->user()->is($user)) {
                return redirect('/base-de-datos/users')->withErrors([
                    'database_delete' => 'No puedes eliminar tu propia cuenta.',
                ]);
            }

            if ($user->is_super_admin && User::query()->where('is_super_admin', true)->where('is_active', true)->count() <= 1) {
                return redirect('/base-de-datos/users')->withErrors([
                    'database_delete' => 'No puedes eliminar al último superadministrador activo.',
                ]);
            }

            DB::transaction(function () use ($user): void {
                $user->forceFill(['is_active' => false])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
            });

            return redirect('/gestion-usuarios?estado=historial')->with(
                'user_status',
                'Usuario guardado en el historial sin eliminar sus datos.',
            );
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
}
