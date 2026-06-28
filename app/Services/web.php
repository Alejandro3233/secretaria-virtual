<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ActivityExportController;
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
use App\Http\Controllers\UserAdminController;
use App\Models\Appointment;
use App\Models\Service as SalonService;
use App\Models\Stylist;
use App\Services\AppointmentReminderCallService;
use App\Services\ClinicResolver;
use App\Services\TwilioSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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

Route::get('/informes/actividad', fn () => redirect('/consola'))->middleware('auth')->name('activity-export');
Route::get('/citas/{appointment}/editar', [AppointmentController::class, 'edit'])->middleware('auth');
Route::put('/citas/{appointment}/recordatorio', [AppointmentController::class, 'reminders'])->middleware('auth');
Route::put('/citas/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->middleware('auth');
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
Route::post('/personal/servicios/{service}/extras', [ServiceController::class, 'storeAddon'])->middleware('auth');
Route::put('/personal/servicios/{service}/extras/{addon}', [ServiceController::class, 'updateAddon'])->middleware('auth');
Route::delete('/personal/servicios/{service}/extras/{addon}', [ServiceController::class, 'destroyAddon'])->middleware('auth');

Route::get('/agenda', [ScheduleController::class, 'index'])->middleware('auth');
Route::get('/agenda/nueva-cita', [ScheduleController::class, 'create'])->middleware('auth');
Route::get('/agenda/clientes/buscar', [ScheduleController::class, 'clients'])->middleware('auth');
Route::post('/agenda/nueva-cita', [ScheduleController::class, 'store'])->middleware('auth');
Route::post('/agenda/citas/{appointment}/mover', [ScheduleController::class, 'move'])->middleware('auth');
Route::put('/agenda/citas/{appointment}/mover', [ScheduleController::class, 'move'])->middleware('auth');

Route::get('/consola', function (Request $request, ClinicResolver $clinics) {
    $clinic = $clinics->currentOrCreate($request->user());
    $timezone = $clinic->localTimezone();
    $now = now($timezone);
    $dateInput = (string) ($request->query('date') ?: $request->query('fecha', ''));
    $selectedRange = in_array($request->query('view'), ['day', 'week', 'month'], true)
        ? (string) $request->query('view')
        : 'day';

    try {
        $selectedDay = $dateInput !== ''
            ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $dateInput, $timezone)->startOfDay()
            : $now->copy()->startOfDay();
    } catch (\Throwable) {
        $selectedDay = $now->copy()->startOfDay();
    }

    $selectedDate = $selectedDay->toDateString();
    $rangeStart = match ($selectedRange) {
        'week' => $selectedDay->copy()->startOfWeek(),
        'month' => $selectedDay->copy()->startOfMonth(),
        default => $selectedDay->copy()->startOfDay(),
    };
    $rangeEnd = match ($selectedRange) {
        'week' => $selectedDay->copy()->endOfWeek(),
        'month' => $selectedDay->copy()->endOfMonth(),
        default => $selectedDay->copy()->endOfDay(),
    };
    $selectedDateLabel = match ($selectedRange) {
        'week' => $rangeStart->isoFormat('D MMM').' - '.$rangeEnd->isoFormat('D MMM'),
        'month' => $selectedDay->isoFormat('MMMM YYYY'),
        default => $selectedDay->isoFormat('dddd D [de] MMMM'),
    };
    $periodLabel = match ($selectedRange) {
        'week' => $rangeStart->copy()->locale('es')->isoFormat('D MMM').' - '.$rangeEnd->copy()->locale('es')->isoFormat('D MMM YYYY'),
        'month' => ucfirst($selectedDay->copy()->locale('es')->isoFormat('MMMM [de] YYYY')),
        default => ucfirst($selectedDay->copy()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')),
    };
    $selectedDateShortLabel = match ($selectedRange) {
        'week' => $rangeStart->isoFormat('D MMM').' - '.$rangeEnd->isoFormat('D MMM'),
        'month' => $selectedDay->isoFormat('MMM YYYY'),
        default => $selectedDay->isoFormat('D MMM'),
    };
    $isSelectedToday = $selectedDate === $now->toDateString() && $selectedRange === 'day';
    $previousDate = match ($selectedRange) {
        'week' => $selectedDay->copy()->subWeek(),
        'month' => $selectedDay->copy()->subMonthNoOverflow(),
        default => $selectedDay->copy()->subDay(),
    };
    $nextDate = match ($selectedRange) {
        'week' => $selectedDay->copy()->addWeek(),
        'month' => $selectedDay->copy()->addMonthNoOverflow(),
        default => $selectedDay->copy()->addDay(),
    };
    $consoleUrl = fn (\Illuminate\Support\Carbon $date, string $view): string => url('/consola').'?date='.$date->toDateString().'&view='.$view;
    $previousDateUrl = $consoleUrl($previousDate, $selectedRange);
    $nextDateUrl = $consoleUrl($nextDate, $selectedRange);
    $todayDateUrl = $consoleUrl($now->copy(), 'day');
    $rangeUrls = [
        'day' => $consoleUrl($selectedDay, 'day'),
        'week' => $consoleUrl($selectedDay, 'week'),
        'month' => $consoleUrl($selectedDay, 'month'),
    ];
    $todayStart = $rangeStart->copy()->timezone(config('app.timezone'));
    $todayEnd = $rangeEnd->copy()->timezone(config('app.timezone'));
    $activeCallStatuses = ['queued', 'initiated', 'ringing', 'in-progress', 'answered', 'received'];

    $todayAppointments = Appointment::query()
        ->with(['client', 'service', 'stylist'])
        ->where('clinic_id', $clinic->id)
        ->whereBetween('starts_at', [$todayStart, $todayEnd])
        ->whereNotIn('status', ['cancelled', 'canceled'])
        ->orderBy('starts_at')
        ->get()
        ->each(function (Appointment $appointment) use ($timezone): void {
            $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
            $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
        });

    $nextAppointment = $todayAppointments
        ->first(fn (Appointment $appointment): bool => $appointment->starts_at->greaterThanOrEqualTo($now));

    $nextReminderCallQuery = Appointment::query()
        ->with(['client', 'service', 'stylist'])
        ->where('clinic_id', $clinic->id)
        ->whereNotIn('status', ['cancelled', 'canceled'])
        ->whereBetween('starts_at', [now(), now()->addHours(169)])
        ->whereHas('client', fn ($query) => $query->whereNotNull('phone')->where('phone', 'not like', 'google:%'))
        ->orderBy('starts_at');

    if (Schema::hasColumn('appointments', 'reminder_call_enabled')) {
        $nextReminderCallQuery->where('reminder_call_enabled', true);
    }

    $nextReminderCall = $clinic->notificationEnabled('appointment_reminder_call')
        ? $nextReminderCallQuery
            ->get()
            ->map(function (Appointment $appointment) use ($clinic, $timezone): Appointment {
                $latestCallNotification = DB::table('notifications')
                    ->where('appointment_id', $appointment->id)
                    ->where('event', 'appointment_reminder_call')
                    ->orderByDesc('created_at')
                    ->first();

                $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
                $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
                $appointment->setAttribute(
                    'scheduled_call_at',
                    $appointment->starts_at->copy()->subHours($clinic->reminderHoursBefore('call')),
                );
                $appointment->setAttribute('reminder_call_status', $latestCallNotification?->status);
                $appointment->setAttribute(
                    'reminder_call_message',
                    app(AppointmentReminderCallService::class)->messageFor($appointment),
                );

                return $appointment;
            })
            ->filter(fn (Appointment $appointment): bool => $appointment->getAttribute('scheduled_call_at')->greaterThanOrEqualTo(now($timezone)->subHour()))
            ->sortBy(fn (Appointment $appointment) => $appointment->getAttribute('scheduled_call_at')->timestamp)
            ->first()
        : null;

    $callsToday = DB::table('call_logs')
        ->where('clinic_id', $clinic->id)
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->count();

    $resolvedCallsToday = DB::table('call_logs')
        ->where('clinic_id', $clinic->id)
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->where(function ($query) {
            $query->whereNotNull('appointment_id')
                ->orWhereIn('status', ['completed', 'resolved']);
        })
        ->count();

    $outboundCallsToday = DB::table('notifications')
        ->where('clinic_id', $clinic->id)
        ->where('channel', 'voice')
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->count();

    $activeCallsCount = DB::table('call_logs')
        ->where('clinic_id', $clinic->id)
        ->whereIn('status', $activeCallStatuses)
        ->where('created_at', '>=', now()->subMinutes(30))
        ->count()
        + DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('channel', 'voice')
            ->whereIn('status', ['queued', 'initiated', 'ringing', 'in-progress', 'answered'])
            ->where('created_at', '>=', now()->subHour())
            ->count();

    $smsToday = DB::table('notifications')
        ->where('clinic_id', $clinic->id)
        ->where('channel', 'sms')
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->count();

    $emailsToday = DB::table('notifications')
        ->where('clinic_id', $clinic->id)
        ->where('channel', 'email')
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->count();

    $latestCalls = DB::table('notifications')
        ->leftJoin('clients', 'notifications.client_id', '=', 'clients.id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'voice')
        ->whereBetween('notifications.created_at', [$todayStart, $todayEnd])
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'clients.phone as client_phone',
            'appointments.starts_at as appointment_starts_at',
        )
        ->orderByDesc('notifications.created_at')
        ->get();

    $activeInboundCall = DB::table('call_logs')
        ->leftJoin('clients', 'call_logs.client_id', '=', 'clients.id')
        ->leftJoin('appointments', 'call_logs.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('call_logs.clinic_id', $clinic->id)
        ->whereIn('call_logs.status', $activeCallStatuses)
        ->where('call_logs.created_at', '>=', now()->subMinutes(30))
        ->select(
            'call_logs.*',
            'clients.first_name',
            'clients.last_name',
            'services.name as service_name',
            'services.duration_minutes',
            'stylists.name as stylist_name',
            'appointments.starts_at as appointment_starts_at',
            'appointments.status as appointment_status',
        )
        ->orderByDesc('call_logs.created_at')
        ->first();

    $activeOutboundCall = DB::table('notifications')
        ->leftJoin('clients', 'notifications.client_id', '=', 'clients.id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'voice')
        ->whereIn('notifications.status', ['queued', 'initiated', 'ringing', 'in-progress', 'answered'])
        ->where('notifications.created_at', '>=', now()->subHour())
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'services.name as service_name',
            'services.duration_minutes',
            'stylists.name as stylist_name',
            'appointments.starts_at as appointment_starts_at',
            'appointments.status as appointment_status',
        )
        ->orderByDesc('notifications.created_at')
        ->first();

    $liveCall = $activeInboundCall ?: $activeOutboundCall;

    $latestInboundCalls = DB::table('call_logs')
        ->leftJoin('clients', 'call_logs.client_id', '=', 'clients.id')
        ->where('call_logs.clinic_id', $clinic->id)
        ->whereBetween('call_logs.created_at', [$todayStart, $todayEnd])
        ->select('call_logs.*', 'clients.first_name', 'clients.last_name')
        ->orderByDesc('call_logs.created_at')
        ->get();

    $answeredCallStatuses = ['sent', 'completed', 'answered', 'received', 'resolved'];
    $missedCallStatuses = ['no-answer', 'busy', 'failed', 'canceled'];
    $callStatusLabel = fn (?string $status): string => match ($status) {
        'sent', 'completed', 'answered', 'received', 'resolved' => 'Contestada',
        'no-answer' => 'No contestada',
        'busy' => 'Ocupado',
        'failed' => 'Fallida',
        'canceled' => 'Cancelada',
        'queued', 'initiated', 'ringing', 'in-progress' => 'En proceso',
        default => ucfirst((string) ($status ?: 'registrada')),
    };
    $callStatusClass = fn (?string $status): string => match ($status) {
        'sent', 'completed', 'answered', 'received', 'resolved' => 'ok',
        'no-answer', 'busy', 'failed', 'canceled' => 'danger',
        'queued', 'initiated', 'ringing', 'in-progress' => 'wait',
        default => 'wait',
    };
    $callDetail = function (object $call, string $source) use ($callStatusClass, $callStatusLabel, $timezone): object {
        $clientName = trim(($call->first_name ?? '').' '.($call->last_name ?? ''));

        return (object) [
            'client_name' => $clientName !== '' ? $clientName : ($call->from_phone ?? $call->recipient ?? 'Cliente'),
            'contact' => $call->recipient ?? $call->from_phone ?? $call->client_phone ?? 'Sin telefono',
            'summary' => $source === 'outbound'
                ? ($call->appointment_starts_at ? 'Recordatorio de cita '.\Illuminate\Support\Carbon::parse($call->appointment_starts_at)->timezone($timezone)->format('d/m g:i A') : 'Recordatorio de voz')
                : ($call->intent ? ucfirst(str_replace('_', ' ', (string) $call->intent)) : 'Llamada entrante'),
            'status_label' => $callStatusLabel($call->status ?? null),
            'status_class' => $callStatusClass($call->status ?? null),
            'occurred_at' => \Illuminate\Support\Carbon::parse($call->sent_at ?? $call->created_at)->timezone($timezone)->format('g:i A'),
        ];
    };
    $answeredCallDetails = $latestCalls
        ->whereIn('status', $answeredCallStatuses)
        ->map(fn (object $call): object => $callDetail($call, 'outbound'))
        ->concat(
            $latestInboundCalls
                ->where('intent', '!=', 'appointment_reminder_call')
                ->whereIn('status', $answeredCallStatuses)
                ->map(fn (object $call): object => $callDetail($call, 'inbound'))
        )
        ->values();
    $missedCallDetails = $latestCalls
        ->whereIn('status', $missedCallStatuses)
        ->map(fn (object $call): object => $callDetail($call, 'outbound'))
        ->concat(
            $latestInboundCalls
                ->where('intent', '!=', 'appointment_reminder_call')
                ->whereIn('status', $missedCallStatuses)
                ->map(fn (object $call): object => $callDetail($call, 'inbound'))
        )
        ->values();
    $answeredCallsToday = $answeredCallDetails->count();
    $missedCallsToday = $missedCallDetails->count();

    $smsDetails = DB::table('notifications')
        ->leftJoin('clients', 'notifications.client_id', '=', 'clients.id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'sms')
        ->whereBetween('notifications.created_at', [$todayStart, $todayEnd])
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'appointments.starts_at as appointment_starts_at',
        )
        ->orderByDesc('notifications.created_at')
        ->get();

    $emailDetails = DB::table('notifications')
        ->leftJoin('clients', 'notifications.client_id', '=', 'clients.id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'email')
        ->whereBetween('notifications.created_at', [$todayStart, $todayEnd])
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'appointments.starts_at as appointment_starts_at',
        )
        ->orderByDesc('notifications.created_at')
        ->get();

    return view('console.index', [
        'clinic' => $clinic,
        'timezone' => $timezone,
        'now' => $now,
        'selectedDate' => $selectedDate,
        'selectedRange' => $selectedRange,
        'selectedDateLabel' => $selectedDateLabel,
        'periodLabel' => $periodLabel,
        'selectedDateShortLabel' => $selectedDateShortLabel,
        'isSelectedToday' => $isSelectedToday,
        'previousDateUrl' => $previousDateUrl,
        'nextDateUrl' => $nextDateUrl,
        'todayDateUrl' => $todayDateUrl,
        'rangeUrls' => $rangeUrls,
        'todayAppointments' => $todayAppointments,
        'nextAppointment' => $nextAppointment,
        'nextReminderCall' => $nextReminderCall,
        'callsToday' => $callsToday,
        'resolvedCallsToday' => $resolvedCallsToday,
        'outboundCallsToday' => $outboundCallsToday,
        'activeCallsCount' => $activeCallsCount,
        'answeredCallsToday' => $answeredCallsToday,
        'missedCallsToday' => $missedCallsToday,
        'smsToday' => $smsToday,
        'emailsToday' => $emailsToday,
        'latestCalls' => $latestCalls,
        'liveCall' => $liveCall,
        'latestInboundCalls' => $latestInboundCalls,
        'answeredCallDetails' => $answeredCallDetails,
        'missedCallDetails' => $missedCallDetails,
        'smsDetails' => $smsDetails,
        'emailDetails' => $emailDetails,
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
    $booleanKeys = [
        'appointment_created_sms',
        'appointment_created_email',
        'appointment_updated_sms',
        'appointment_updated_email',
        'appointment_reminder_sms',
        'appointment_reminder_call',
        'appointment_reschedule_link_sms',
    ];

    $data = $request->validate([
        'appointment_reminder_sms_hours_before' => ['required', 'integer', 'min:1', 'max:168'],
        'appointment_reminder_call_hours_before' => ['required', 'integer', 'min:1', 'max:168'],
    ]);

    $preferences = collect($booleanKeys)
        ->mapWithKeys(fn (string $key): array => [$key => $request->boolean($key)])
        ->all();
    $preferences['appointment_reminder_sms_hours_before'] = (int) $data['appointment_reminder_sms_hours_before'];
    $preferences['appointment_reminder_call_hours_before'] = (int) $data['appointment_reminder_call_hours_before'];

    $clinic->forceFill([
        'notification_preferences' => $preferences,
    ])->save();

    return redirect('/ajustes#notificaciones')->with('settings_status', 'Preferencias de notificaciones actualizadas.');
})->middleware('auth');

Route::post('/ajustes/contacto/codigo', function (Request $request, TwilioSmsService $sms) {
    $user = $request->user();

    $data = $request->validate([
        'mobile_phone' => [
            'required',
            'string',
            'max:40',
            Rule::unique('users', 'mobile_phone')->ignore($user->id),
        ],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($user->id),
        ],
    ], [
        'mobile_phone.unique' => 'Ya existe un usuario con este telefono.',
        'email.unique' => 'Ya existe un usuario con este correo electronico.',
    ]);

    if (! $user->mobile_phone) {
        return redirect('/ajustes#numero-cliente')->with('settings_error', 'Necesitas tener un movil registrado para validar este cambio por SMS.');
    }

    if ($data['mobile_phone'] === $user->mobile_phone && $data['email'] === $user->email) {
        return redirect('/ajustes#numero-cliente')->with('settings_error', 'Escribe un telefono o correo diferente para solicitar el cambio.');
    }

    $code = (string) random_int(100000, 999999);
    $message = "Tu codigo de seguridad de Secretary365 es {$code}. Usalo para confirmar el cambio de telefono o correo. Expira en 10 minutos.";

    try {
        $providerMessageId = $sms->send($user->mobile_phone, $message);
    } catch (\Throwable $exception) {
        return redirect('/ajustes#numero-cliente')->with('settings_error', 'No se pudo enviar el codigo por SMS: '.$exception->getMessage());
    }

    if (! $providerMessageId) {
        return redirect('/ajustes#numero-cliente')->with('settings_error', 'No se pudo enviar el codigo por SMS. Revisa la configuracion de Twilio.');
    }

    session([
        'settings_contact_change' => [
            'mobile_phone' => $data['mobile_phone'],
            'email' => $data['email'],
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ],
    ]);

    return redirect('/ajustes#numero-cliente')->with('settings_status', 'Te enviamos un codigo al movil actual '.$user->mobile_phone.'. Escribelo para confirmar el cambio.');
})->middleware('auth')->name('settings.contact-code');

Route::post('/ajustes/contacto/confirmar', function (Request $request) {
    $data = $request->validate([
        'code' => ['required', 'digits:6'],
    ]);

    $pendingChange = session('settings_contact_change');

    if (! is_array($pendingChange)) {
        return redirect('/ajustes#numero-cliente')->with('settings_error', 'Primero solicita un codigo de verificacion.');
    }

    if (now()->greaterThan(\Illuminate\Support\Carbon::parse($pendingChange['expires_at'] ?? now()->subMinute()))) {
        session()->forget('settings_contact_change');

        return redirect('/ajustes#numero-cliente')->with('settings_error', 'El codigo expiro. Solicita uno nuevo.');
    }

    if (! Hash::check($data['code'], (string) ($pendingChange['code_hash'] ?? ''))) {
        return redirect('/ajustes#numero-cliente')->with('settings_error', 'El codigo no coincide. Revisa el SMS e intentalo de nuevo.');
    }

    $user = $request->user();
    $clinic = $user->primaryClinic();

    $user->forceFill([
        'mobile_phone' => $pendingChange['mobile_phone'],
        'email' => $pendingChange['email'],
    ])->save();

    if ($clinic) {
        $clinic->forceFill([
            'phone' => $pendingChange['mobile_phone'],
            'email' => $pendingChange['email'],
        ])->save();
    }

    session()->forget('settings_contact_change');

    return redirect('/ajustes#numero-cliente')->with('settings_status', 'Telefono y correo actualizados correctamente.');
})->middleware('auth')->name('settings.contact-confirm');

Route::get('/dashboard', function () {
    return redirect('/consola');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/gestion-usuarios', [UserAdminController::class, 'index'])->name('users.index');
    Route::post('/gestion-usuarios', [UserAdminController::class, 'store'])->name('users.store');
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
    Route::post('/voz-secretaria/probar-llamada', [GoogleTextToSpeechController::class, 'previewCall'])->name('secretary-voice.preview-call');
    Route::post('/voz-secretaria/activar', [GoogleTextToSpeechController::class, 'activate'])->name('secretary-voice.activate');
    Route::post('/twilio/numeros/asignar', [TwilioPhoneNumberController::class, 'assign'])->name('twilio-number.assign');
});
