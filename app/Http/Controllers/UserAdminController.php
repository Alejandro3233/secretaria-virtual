<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $section = in_array($request->query('estado'), ['activos', 'deshabilitados', 'historial'], true)
            ? (string) $request->query('estado')
            : 'activos';

        $users = match ($section) {
            'deshabilitados' => User::query()->where('is_active', false),
            'historial' => User::onlyTrashed(),
            default => User::query()->where('is_active', true),
        };

        return view('users.index', [
            'users' => $users
                ->with('clinics')
                ->orderByDesc('created_at')
                ->get(),
            'clinics' => Clinic::query()->orderBy('name')->get(),
            'section' => $section,
            'userCounts' => [
                'activos' => User::query()->where('is_active', true)->count(),
                'deshabilitados' => User::query()->where('is_active', false)->count(),
                'historial' => User::onlyTrashed()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'mobile_phone' => ['nullable', 'string', 'max:40', Rule::unique('users', 'mobile_phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'clinic_id' => ['required', 'integer', Rule::exists('clinics', 'id')],
            'role' => ['required', Rule::in(['owner', 'staff'])],
            'is_super_admin' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Ya existe un usuario con este correo electronico.',
            'mobile_phone.unique' => 'Ya existe un usuario con este telefono movil.',
            'password.confirmed' => 'La confirmacion de la contrasena no coincide.',
            'clinic_id.required' => 'Selecciona la clinica a la que tendra acceso el usuario.',
        ]);

        DB::transaction(function () use ($data, $request): void {
            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'password' => $data['password'],
                'is_super_admin' => $request->boolean('is_super_admin'),
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $user->clinics()->attach($data['clinic_id'], [
                'role' => $data['role'],
            ]);
        });

        return redirect('/gestion-usuarios')->with('user_status', 'Usuario creado y asignado correctamente.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $activate = (bool) $data['is_active'];

        if ($request->user()->is($user) && ! $activate) {
            return back()->with('user_error', 'No puedes deshabilitar tu propia cuenta.');
        }

        if (! $activate && $this->isLastSuperAdmin($user)) {
            return back()->with('user_error', 'No puedes deshabilitar al último superadministrador activo.');
        }

        $user->update(['is_active' => $activate]);
        if (! $activate) {
            $this->closeSessions($user);
        }

        return redirect()->route('users.index', [
            'estado' => $activate ? 'activos' : 'deshabilitados',
        ])->with('user_status', $activate ? 'Usuario habilitado correctamente.' : 'Usuario deshabilitado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        if ($request->user()->is($user)) {
            return back()->with('user_error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($this->isLastSuperAdmin($user)) {
            return back()->with('user_error', 'No puedes eliminar al último superadministrador activo.');
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['is_active' => false])->save();
            $this->closeSessions($user);
            $user->delete();
        });

        return redirect()->route('users.index')->with(
            'user_status',
            'Usuario retirado de la plataforma y guardado en el historial.',
        );
    }

    public function restore(Request $request, int $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $archivedUser = User::onlyTrashed()->findOrFail($user);
        $archivedUser->restore();
        $archivedUser->update(['is_active' => true]);

        return redirect()->route('users.index')->with('user_status', 'Usuario restaurado y habilitado correctamente.');
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return $user->is_super_admin
            && User::query()->where('is_super_admin', true)->where('is_active', true)->count() <= 1;
    }

    private function closeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403);
    }
}
