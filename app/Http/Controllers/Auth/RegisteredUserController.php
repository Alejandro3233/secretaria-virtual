<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CallForwardingOnboardingService;
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

    public function store(Request $request, TwilioPhoneNumberService $twilioNumbers, CallForwardingOnboardingService $onboarding): RedirectResponse
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
                'is_active' => true,
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
                'google_tts_voice' => 'twilio-google-es-us-neural2-a',
                'notification_preferences' => [
                    'nora_language' => 'es',
                ],
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

        return redirect()->intended($onboarding->destinationFor($user));
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
        $html = $this->welcomeEmailHtml($user, $clinic);

        try {
            Mail::send([], [], function ($message) use ($body, $html, $user, $clinic): void {
                $message
                    ->to($user->email)
                    ->subject('Bienvenido a Secretaria Virtual - '.$clinic->name);

                $message->getSymfonyMessage()
                    ->html($html)
                    ->text($body);
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

    private function welcomeEmailHtml(User $user, Clinic $clinic): string
    {
        $fullName = trim($user->name.' '.($user->last_name ?? '')) ?: 'cliente';
        $appName = config('app.name') === 'Laravel' ? 'Secretaria Virtual' : (string) config('app.name');
        $loginUrl = url('/login');
        $consoleUrl = url('/consola');

        return '<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Secretaria Virtual</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e8ecf2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#111827;padding:30px;color:#ffffff;">
                            <div style="font-size:13px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#f9c7d8;">'.$this->escape($appName).'</div>
                            <h1 style="margin:10px 0 0;font-size:28px;line-height:1.25;font-weight:900;">Tu cuenta ya esta lista</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">Hola <strong>'.$this->escape($fullName).'</strong>,</p>
                            <p style="margin:0 0 22px;font-size:16px;line-height:1.55;">Bienvenido a Secretaria Virtual. Tu salon <strong>'.$this->escape($clinic->name).'</strong> fue activado correctamente y ya puedes empezar a gestionar tu operacion desde la consola.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:8px;margin:0 0 22px;">
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Salon</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;">'.$this->escape($clinic->name).'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Correo</td>
                                    <td align="right" style="padding:16px 18px;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:900;">'.$this->escape($user->email).'</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;color:#6b7280;font-size:13px;font-weight:800;text-transform:uppercase;">Estado</td>
                                    <td align="right" style="padding:16px 18px;font-size:15px;font-weight:900;color:#166534;">Activo</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding:4px 0 22px;">
                                        <a href="'.$this->escape($consoleUrl).'" style="display:inline-block;background:#c0265a;color:#ffffff;text-decoration:none;font-weight:800;border-radius:6px;padding:13px 18px;">Abrir consola</a>
                                        <a href="'.$this->escape($loginUrl).'" style="display:inline-block;margin-left:8px;background:#ffffff;color:#111827;text-decoration:none;font-weight:800;border:1px solid #d1d5db;border-radius:6px;padding:12px 17px;">Iniciar sesion</a>
                                    </td>
                                </tr>
                            </table>

                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;color:#475569;font-size:14px;line-height:1.55;">
                                Desde tu consola puedes gestionar agenda, clientes, servicios, recordatorios, llamadas, SMS y sincronizacion con Google Calendar.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 30px;background:#fbfcfe;border-top:1px solid #edf0f5;color:#6b7280;font-size:13px;line-height:1.5;">
                            Gracias por confiar en <strong style="color:#374151;">'.$this->escape($appName).'</strong>.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
