<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TwilioPhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, TwilioPhoneNumberService $twilioNumbers): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'email_confirmation' => ['required', 'string', 'lowercase', 'email', 'same:email'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'clinic_phone' => ['nullable', 'string', 'max:40'],
            'country_code' => ['required', 'string', Rule::in(array_keys($twilioNumbers->supportedCountries()))],
        ], [
            'email.unique' => 'Usuario ya registrado. Inicia sesion o usa recuperar contrasena.',
            'email_confirmation.same' => 'La confirmacion del correo electronico no coincide.',
            'password_confirmation.same' => 'La confirmacion de la contrasena no coincide.',
        ]);

        $clinic = null;

        $user = DB::transaction(function () use ($data, &$clinic): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $plan = SubscriptionPlan::query()
                ->where('slug', 'profesional')
                ->first();

            $clinic = Clinic::create([
                'subscription_plan_id' => $plan?->id,
                'name' => 'Salon de '.$data['name'],
                'phone' => $data['clinic_phone'] ?? null,
                'country_code' => $data['country_code'],
                'email' => $data['email'],
                'subscription_status' => 'trial',
            ]);

            DB::table('clinic_users')->insert([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        if ($clinic && $twilioNumbers->autoBuyEnabled()) {
            $twilioNumbers->assignToClinic($clinic);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended('/consola');
    }
}
