<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DatabaseAdminController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\GoogleTextToSpeechController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicRescheduleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StripeSubscriptionController;
use App\Http\Controllers\TwilioPhoneNumberController;
use App\Models\Appointment;
use App\Models\Service as SalonService;
use App\Models\Stylist;
use App\Services\ClinicResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/particular', [PublicBookingController::class, 'index'])->name('public-bookings.index');
Route::get('/salones/{clinic}', [PublicBookingController::class, 'show'])->name('public-bookings.show');
Route::get('/salones/{clinic}/reservar', [PublicBookingController::class, 'create'])->name('public-bookings.create');
Route::post('/salones/{clinic}/reservar', [PublicBookingController::class, 'store'])->name('public-bookings.store');
Route::get('/cita/{appointment}/{token}/confirmar', [PublicRescheduleController::class, 'confirm'])->name('public-appointments.confirm');
Route::get('/cita/{appointment}/{token}/cancelar', [PublicRescheduleController::class, 'cancel'])->name('public-appointments.cancel');
Route::get('/reagendar/{appointment}/{token}', [PublicRescheduleController::class, 'show'])->name('public-reschedule.show');
Route::post('/reagendar/{appointment}/{token}', [PublicRescheduleController::class, 'update'])->name('public-reschedule.update');

Route::get('/citas', [AppointmentController::class, 'index'])->middleware('auth');
Route::get('/citas/{appointment}/editar', [AppointmentController::class, 'edit'])->middleware('auth');
Route::put('/citas/{appointment}/recordatorio', [AppointmentController::class, 'reminders'])->middleware('auth');
Route::put('/citas/{appointment}', [AppointmentController::class, 'update'])->middleware('auth');
Route::delete('/citas/{appointment}', [AppointmentController::class, 'destroy'])->middleware('auth');

Route::get('/personal', [StaffController::class, 'index'])->middleware('auth');
Route::post('/personal', [StaffController::class, 'store'])->middleware('auth');
Route::put('/personal/{stylist}', [StaffController::class, 'update'])->middleware('auth');
Route::delete('/personal/{stylist}', [StaffController::class, 'destroy'])->middleware('auth');
Route::get('/personal/servicios', [ServiceController::class, 'index'])->middleware('auth');
Route::post('/personal/servicios', [ServiceController::class, 'store'])->middleware('auth');
Route::post('/personal/servicios/catalogo-base', [ServiceController::class, 'seedTemplates'])->middleware('auth');
Route::put('/personal/servicios/{service}', [ServiceController::class, 'update'])->middleware('auth');

Route::get('/agenda', [ScheduleController::class, 'index'])->middleware('auth');
Route::get('/agenda/nueva-cita', [ScheduleController::class, 'create'])->middleware('auth');
Route::get('/agenda/clientes/buscar', [ScheduleController::class, 'clients'])->middleware('auth');
Route::post('/agenda/nueva-cita', [ScheduleController::class, 'store'])->middleware('auth');
Route::post('/agenda/citas/{appointment}/mover', [ScheduleController::class, 'move'])->middleware('auth');
Route::put('/agenda/citas/{appointment}/mover', [ScheduleController::class, 'move'])->middleware('auth');

Route::get('/consola', function (Request $request, ClinicResolver $clinics) {
    $clinic = $clinics->currentOrCreate($request->user());
    $today = now();

    $todayAppointments = Appointment::query()
        ->with(['client', 'service', 'stylist'])
        ->where('clinic_id', $clinic->id)
        ->whereDate('starts_at', $today)
        ->whereNotIn('status', ['cancelled', 'canceled'])
        ->orderBy('starts_at')
        ->get();

    $serviceBreakdown = $todayAppointments
        ->map(fn (Appointment $appointment) => $appointment->service?->name ?? $appointment->reason ?? 'Cita')
        ->countBy()
        ->sortDesc()
        ->take(3)
        ->map(fn (int $count, string $service) => "{$count} {$service}")
        ->values()
        ->implode(', ');

    $callsToday = DB::table('call_logs')
        ->where('clinic_id', $clinic->id)
        ->whereDate('created_at', $today)
        ->count();

    $resolvedCallsToday = DB::table('call_logs')
        ->where('clinic_id', $clinic->id)
        ->whereDate('created_at', $today)
        ->where(function ($query) {
            $query->whereNotNull('appointment_id')
                ->orWhereIn('status', ['completed', 'resolved']);
        })
        ->count();

    $smsToday = DB::table('notifications')
        ->where('clinic_id', $clinic->id)
        ->where('channel', 'sms')
        ->whereDate('created_at', $today)
        ->count();

    $savedSlotsToday = Appointment::query()
        ->where('clinic_id', $clinic->id)
        ->whereDate('updated_at', $today)
        ->whereIn('status', ['cancelled', 'canceled', 'rescheduled'])
        ->count();

    $upcomingAppointments = $todayAppointments
        ->filter(fn (Appointment $appointment) => $appointment->starts_at->greaterThanOrEqualTo(now()->subMinutes(15)))
        ->take(6)
        ->values();

    $latestCalls = DB::table('call_logs')
        ->leftJoin('clients', 'call_logs.client_id', '=', 'clients.id')
        ->where('call_logs.clinic_id', $clinic->id)
        ->select('call_logs.*', 'clients.first_name', 'clients.last_name')
        ->orderByDesc('call_logs.created_at')
        ->limit(3)
        ->get();

    return view('console.index', [
        'clinic' => $clinic,
        'todayAppointments' => $todayAppointments,
        'serviceBreakdown' => $serviceBreakdown,
        'callsToday' => $callsToday,
        'resolvedCallsToday' => $resolvedCallsToday,
        'smsToday' => $smsToday,
        'savedSlotsToday' => $savedSlotsToday,
        'upcomingAppointments' => $upcomingAppointments,
        'latestCalls' => $latestCalls,
    ]);
})->middleware('auth');

Route::get('/buscar', function (Request $request) {
    $clinic = $request->user()->primaryClinic();
    abort_unless($clinic, 404);

    $query = trim((string) $request->query('q', ''));
    $appointments = collect();
    $services = collect();
    $stylists = collect();

    if ($query !== '') {
        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->where(function ($appointmentsQuery) use ($query) {
                $appointmentsQuery
                    ->where('reason', 'like', "%{$query}%")
                    ->orWhere('status', 'like', "%{$query}%")
                    ->orWhere('chair_station', 'like', "%{$query}%")
                    ->orWhereHas('client', function ($clientQuery) use ($query) {
                        $clientQuery
                            ->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    })
                    ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('stylist', fn ($stylistQuery) => $stylistQuery->where('name', 'like', "%{$query}%"));
            })
            ->orderBy('starts_at')
            ->limit(20)
            ->get();

        $services = SalonService::query()
            ->where('clinic_id', $clinic->id)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get();

        $stylists = Stylist::query()
            ->where('clinic_id', $clinic->id)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    return view('search.index', [
        'query' => $query,
        'appointments' => $appointments,
        'services' => $services,
        'stylists' => $stylists,
    ]);
})->middleware('auth');

Route::get('/ajustes', function () {
    return view('settings.index');
})->middleware('auth');
Route::post('/ajustes/notificaciones', function (Request $request, ClinicResolver $clinics) {
    $clinic = $clinics->currentOrCreate($request->user());
    $keys = array_keys(\App\Models\Clinic::DEFAULT_NOTIFICATION_PREFERENCES);

    $preferences = collect($keys)
        ->mapWithKeys(fn (string $key): array => [$key => $request->boolean($key)])
        ->all();

    $clinic->forceFill([
        'notification_preferences' => $preferences,
    ])->save();

    return redirect('/ajustes#notificaciones')->with('settings_status', 'Preferencias de notificaciones actualizadas.');
})->middleware('auth');

Route::get('/dashboard', function () {
    return redirect('/consola');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/base-de-datos/{table?}', [DatabaseAdminController::class, 'index'])->name('database.index');
    Route::post('/base-de-datos/{table}/{id}', [DatabaseAdminController::class, 'update'])->name('database.update');
    Route::post('/base-de-datos/{table}/{id}/eliminar', [DatabaseAdminController::class, 'destroy'])->name('database.destroy');
});

Route::middleware('guest')->group(function () {
    Route::get('/registro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/registro', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.store');

    Route::get('/auth/google/redirect', [GoogleAuthenticatedSessionController::class, 'redirect'])->name('google.login');
    Route::get('/auth/google/callback', [GoogleAuthenticatedSessionController::class, 'callback'])->name('google.login.callback');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/twilio/voice/incoming', [TwilioPhoneNumberController::class, 'incoming'])->name('twilio.voice.incoming');
Route::post('/twilio/voice/reminder/{appointment}/{token}', [TwilioPhoneNumberController::class, 'reminder'])->name('twilio.voice.reminder');
Route::post('/twilio/voice/reminder/{appointment}/{token}/choice', [TwilioPhoneNumberController::class, 'reminderChoice'])->name('twilio.voice.reminder-choice');
Route::post('/twilio/voice/reminder/status', [TwilioPhoneNumberController::class, 'reminderStatus'])->name('twilio.voice.reminder-status');
Route::post('/stripe/webhook', [StripeSubscriptionController::class, 'webhook'])->name('stripe.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/facturacion', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/facturacion/facturas/{invoice}/pdf', [BillingController::class, 'pdf'])->name('billing.invoice.pdf');
    Route::post('/planes/{plan}/checkout', [StripeSubscriptionController::class, 'checkout'])->name('stripe.checkout');
    Route::get('/stripe/exito', [StripeSubscriptionController::class, 'success'])->name('stripe.success');
    Route::get('/stripe/cancelado', [StripeSubscriptionController::class, 'cancel'])->name('stripe.cancel');
    Route::get('/google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('google-calendar.connect');
    Route::get('/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('google-calendar.callback');
    Route::post('/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google-calendar.disconnect');
    Route::post('/google-calendar/sync', [GoogleCalendarController::class, 'sync'])->name('google-calendar.sync');
    Route::get('/voz-secretaria/prueba', [GoogleTextToSpeechController::class, 'preview'])->name('secretary-voice.preview');
    Route::post('/voz-secretaria/activar', [GoogleTextToSpeechController::class, 'activate'])->name('secretary-voice.activate');
    Route::post('/twilio/numeros/asignar', [TwilioPhoneNumberController::class, 'assign'])->name('twilio-number.assign');
});
