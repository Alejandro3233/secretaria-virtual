<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DatabaseAdminController extends Controller
{
    private const TABLES = [
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

        $table = $this->resolveTable($table);
        $columns = Schema::getColumnListing($table);
        $rows = DB::table($table)->orderBy('id')->limit(100)->get();
        $counts = collect(self::TABLES)->mapWithKeys(fn (string $name) => [$name => DB::table($name)->count()]);

        return view('database.index', [
            'tables' => self::TABLES,
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'counts' => $counts,
            'lockedColumns' => self::LOCKED_COLUMNS,
        ]);
    }

    public function update(Request $request, string $table, int $id): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $table = $this->resolveTable($table);

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

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403);
    }

    private function resolveTable(?string $table): string
    {
        $table = $table ?: self::TABLES[0];

        abort_unless(in_array($table, self::TABLES, true), 404);

        return $table;
    }
}
