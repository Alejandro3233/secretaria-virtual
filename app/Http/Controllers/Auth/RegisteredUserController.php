<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GoogleTextToSpeechService;
use App\Services\TwilioPhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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
        $usersHasLastName = Schema::hasColumn('users', 'last_name');
        $usersHasMobilePhone = Schema::hasColumn('users', 'mobile_phone');

        $data = $request->validate([
            'clinic_name' => ['required', 'string', 'max:255'],
            'clinic_address' => ['required', 'string', 'max:500'],
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'email_confirmation' => ['required', 'string', 'lowercase', 'email', 'same:email'],
            'mobile_phone' => array_filter([
                'required',
                'string',
                'max:40',
                $usersHasMobilePhone ? Rule::unique('users', 'mobile_phone') : null,
            ]),
            'mobile_phone_confirmation' => ['required', 'string', 'max:40', 'same:mobile_phone'],
            'clinic_phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'country_code' => ['required', 'string', Rule::in(array_keys($twilioNumbers->supportedCountries()))],
        ], [
            'email.unique' => 'Usuario ya registrado. Inicia sesion o usa recuperar contrasena.',
            'email_confirmation.same' => 'La confirmacion del correo electronico no coincide.',
            'mobile_phone.unique' => 'Telefono movil ya registrado. Inicia sesion o usa otro numero.',
            'mobile_phone_confirmation.same' => 'La confirmacion del telefono movil no coincide.',
            'password_confirmation.same' => 'La confirmacion de la contrasena no coincide.',
        ]);

        $clinic = null;

        $user = DB::transaction(function () use ($data, $usersHasLastName, $usersHasMobilePhone, &$clinic): User {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ];

            if ($usersHasLastName) {
                $userData['last_name'] = $data['last_name'];
            }

            if ($usersHasMobilePhone) {
                $userData['mobile_phone'] = $data['mobile_phone'];
            }

            $user = User::create($userData);

            $plan = SubscriptionPlan::query()
                ->where('slug', 'profesional')
                ->first();

            $clinic = Clinic::create([
                'subscription_plan_id' => $plan?->id,
                'name' => $data['clinic_name'],
                'phone' => $data['clinic_phone'] ?? null,
                'country_code' => $data['country_code'],
                'timezone' => Clinic::timezoneForCountry($data['country_code']),
                'email' => $data['email'],
                'address' => $data['clinic_address'],
                'subscription_status' => 'trial',
                'google_tts_voice' => GoogleTextToSpeechService::TWILIO_VOICE_ID,
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

        if ($clinic) {
            $this->sendWelcomeEmail($user, $clinic);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended('/consola');
    }

    private function sendWelcomeEmail(User $user, Clinic $clinic): void
    {
        $body = implode("\n", [
            'Hola '.trim($user->name.' '.($user->last_name ?? '')).',',
            '',
            'Bienvenido a Secretaria Virtual.',
            '',
            'Tu cuenta fue creada correctamente y el salon '.$clinic->name.' ya esta activo en la plataforma.',
            '',
            'Desde tu consola puedes gestionar agenda, clientes, servicios, recordatorios, llamadas, SMS y sincronizacion con Google Calendar.',
            '',
            'Para entrar de nuevo, usa este enlace:',
            url('/login'),
            '',
            'Gracias por confiar en Secretaria Virtual.',
        ]);

        try {
            Mail::raw($body, function ($message) use ($user, $clinic): void {
                $message
                    ->to($user->email)
                    ->subject('Bienvenido a Secretaria Virtual - '.$clinic->name);
            });

            DB::table('notifications')->insert([
                'clinic_id' => $clinic->id,
                'client_id' => null,
                'appointment_id' => null,
                'channel' => 'email',
                'event' => 'salon_registered',
                'recipient' => $user->email,
                'status' => 'sent',
                'provider_message_id' => null,
                'body' => $body,
                'error' => null,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar email de bienvenida al salon.', [
                'user_id' => $user->id,
                'clinic_id' => $clinic->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
