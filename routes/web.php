<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentPaymentController;
use App\Http\Controllers\ActivityExportController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DatabaseAdminController;
use App\Http\Controllers\DailyBriefingController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\GoogleTextToSpeechController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\NoraClientCallController;
use App\Http\Controllers\NoraReminderController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicRescheduleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleOptimizationController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StripeSubscriptionController;
use App\Http\Controllers\SuperAdminCostController;
use App\Http\Controllers\TwilioPhoneNumberController;
use App\Http\Controllers\UserAdminController;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\Service as SalonService;
use App\Models\Stylist;
use App\Services\AppointmentReminderCallService;
use App\Services\ClinicResolver;
use App\Services\CallForwardingInstructionService;
use App\Services\DailyBriefingService;
use App\Services\ScheduleOptimizationService;
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
Route::view('/pagos/exito', 'payments.success')->name('appointment-payments.success');
Route::view('/pagos/cancelado', 'payments.cancel')->name('appointment-payments.cancel');
Route::get('/cita/{appointment}/adelantar', [ScheduleOptimizationController::class, 'show'])->middleware('signed')->name('schedule-optimization.show');
Route::post('/cita/{appointment}/adelantar', [ScheduleOptimizationController::class, 'accept'])->middleware('signed');

Route::get('/citas', [AppointmentController::class, 'index'])->middleware('auth');
Route::get('/informes/actividad', ActivityExportController::class)->middleware('auth')->name('activity-export');
Route::get('/citas/{appointment}/editar', [AppointmentController::class, 'edit'])->middleware('auth');
Route::put('/citas/{appointment}/recordatorio', [AppointmentController::class, 'reminders'])->middleware('auth');
Route::post('/citas/{appointment}/pagos', [AppointmentPaymentController::class, 'store'])->middleware('auth')->name('appointments.payments.store');
Route::post('/citas/{appointment}/llamar-ahora', [AppointmentController::class, 'callNow'])->middleware('auth')->name('appointments.call-now');
Route::put('/citas/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->middleware('auth');
Route::put('/citas/{appointment}', [AppointmentController::class, 'update'])->middleware('auth');
Route::delete('/citas/{appointment}', [AppointmentController::class, 'destroy'])->middleware('auth');

Route::middleware('auth')->group(function (): void {
    Route::get('/clientes', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clientes/nuevo', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clientes', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clientes/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clientes/{client}/editar', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clientes/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::put('/clientes/{client}/notas', [ClientController::class, 'notes'])->name('clients.notes');
    Route::put('/clientes/{client}/clasificacion', [ClientController::class, 'loyalty'])->name('clients.loyalty');
    Route::put('/clientes/{client}/citas/{appointment}/asistencia', [ClientController::class, 'attendance'])->name('clients.attendance');
});

Route::get('/personal', [StaffController::class, 'index'])->middleware('auth');
Route::post('/personal', [StaffController::class, 'store'])->middleware('auth');
Route::get('/personal/vacaciones', [StaffController::class, 'vacations'])->middleware('auth')->name('staff.vacations.index');
Route::put('/personal/{stylist}', [StaffController::class, 'update'])->middleware('auth');
Route::post('/personal/{stylist}/vacaciones', [StaffController::class, 'storeVacation'])->middleware('auth');
Route::delete('/personal/{stylist}/vacaciones/{vacation}', [StaffController::class, 'destroyVacation'])->middleware('auth');
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

Route::get('/consola/llamada-activa', function (Request $request, ClinicResolver $clinics) {
    $clinic = $clinics->currentOrCreate($request->user());

    $inbound = DB::table('call_logs')
        ->leftJoin('clients', 'call_logs.client_id', '=', 'clients.id')
        ->where('call_logs.clinic_id', $clinic->id)
        ->where(function ($query): void {
            $query->where(function ($active): void {
                $active->whereIn('call_logs.status', ['in-progress', 'answered'])
                    ->where('call_logs.created_at', '>=', now()->subHours(6));
            })->orWhere(function ($ringing): void {
                $ringing->whereIn('call_logs.status', ['queued', 'initiated', 'ringing', 'received'])
                    ->where('call_logs.created_at', '>=', now()->subMinutes(2));
            });
        })
        ->select('call_logs.twilio_call_sid', 'call_logs.status', 'call_logs.from_phone as phone', 'call_logs.created_at', 'clients.first_name', 'clients.last_name', DB::raw("'inbound' as direction"))
        ->orderByDesc('call_logs.created_at')
        ->first();

    $outbound = DB::table('notifications')
        ->leftJoin('clients', 'notifications.client_id', '=', 'clients.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'voice')
        ->where(function ($query): void {
            $query->where(function ($pending): void {
                $pending->whereIn('notifications.status', ['queued', 'initiated', 'ringing'])
                    ->where('notifications.created_at', '>=', now()->subMinutes(2));
            })->orWhere(function ($connected): void {
                $connected->whereIn('notifications.status', ['in-progress', 'answered'])
                    ->where('notifications.created_at', '>=', now()->subMinutes(10));
            });
        })
        ->select('notifications.status', 'notifications.recipient as phone', 'notifications.created_at', 'clients.first_name', 'clients.last_name', DB::raw("'outbound' as direction"))
        ->orderByDesc('notifications.created_at')
        ->first();

    $call = collect([$inbound, $outbound])
        ->filter()
        ->sortByDesc(fn ($candidate) => \Illuminate\Support\Carbon::parse($candidate->created_at)->timestamp)
        ->first();

    if (! $call) {
        return response()->json(['active' => false]);
    }

    $clientName = trim(($call->first_name ?? '').' '.($call->last_name ?? ''));
    $statusLabel = match ($call->status) {
        'queued' => 'En cola',
        'initiated' => 'Iniciando llamada',
        'ringing' => 'Teléfono sonando',
        'in-progress', 'answered', 'received' => 'Llamada en curso',
        default => 'Llamada detectada',
    };

    return response()->json([
        'active' => true,
        'status' => $call->status,
        'status_label' => $statusLabel,
        'client' => $clientName !== '' ? $clientName : ($call->phone ?: 'Número desconocido'),
        'phone' => $call->phone,
        'direction' => $call->direction,
        'call_sid' => $call->twilio_call_sid ?? null,
        'expires_in_ms' => $call->direction === 'inbound'
            ? max(0, now()->diffInMilliseconds(\Illuminate\Support\Carbon::parse($call->created_at)->addSeconds((int) config('services.twilio.browser_ring_timeout', 18)), false))
            : null,
    ]);
})->middleware('auth');

Route::post('/consola/optimizar/{appointment}/enviar', [ScheduleOptimizationController::class, 'send'])
    ->middleware('auth')
    ->name('schedule-optimization.send');
Route::post('/consola/resumen-diario/escuchado', [DailyBriefingController::class, 'played'])
    ->middleware('auth')
    ->name('console.daily-briefing.played');

Route::middleware('auth')->group(function (): void {
    Route::post('/consola/nora/llamar-cliente', [NoraClientCallController::class, 'store'])->name('nora-client-calls.store');
    Route::post('/consola/nora/recordar-cita-cliente', [NoraClientCallController::class, 'remindClientAppointment'])->name('nora-client-appointment-reminders.store');
    Route::post('/consola/nora/guardar-nota-cliente', [NoraClientCallController::class, 'storeClientNote'])->name('nora-client-notes.store');
    Route::post('/consola/nora/avisar-retraso-proxima-cita', [NoraClientCallController::class, 'notifyNextAppointmentDelay'])->name('nora-next-appointment-delay.store');
    Route::get('/consola/nora/cobros-hoy', function (Request $request, ClinicResolver $clinics) {
        $clinic = $clinics->currentOrCreate($request->user());
        $timezone = $clinic->localTimezone();
        $start = now($timezone)->startOfDay()->timezone(config('app.timezone'));
        $end = now($timezone)->endOfDay()->timezone(config('app.timezone'));
        $payments = AppointmentPayment::query()
            ->where('clinic_id', $clinic->id)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->get();
        $total = (int) $payments->sum('amount_cents');
        $cash = (int) $payments->where('method', 'cash')->sum('amount_cents');
        $stripe = (int) $payments->where('method', 'stripe')->sum('amount_cents');
        $other = (int) $payments->where('method', 'other')->sum('amount_cents');

        return response()->json([
            'status' => 'ok',
            'message' => $total > 0
                ? 'Hoy llevamos cobrado '.number_format($total / 100, 2).' dolares. En efectivo '.number_format($cash / 100, 2).', por Stripe '.number_format($stripe / 100, 2).' y por otros metodos '.number_format($other / 100, 2).'.'
                : 'Hoy todavia no hay pagos cobrados registrados.',
            'total_cents' => $total,
            'cash_cents' => $cash,
            'stripe_cents' => $stripe,
            'other_cents' => $other,
        ]);
    })->name('nora-payments.today');
    Route::get('/nora/recordatorios', [NoraReminderController::class, 'index'])->name('nora-reminders.index');
    Route::post('/nora/recordatorios', [NoraReminderController::class, 'store'])->name('nora-reminders.store');
    Route::post('/nora/recordatorios/cancelar', [NoraReminderController::class, 'cancel'])->name('nora-reminders.cancel');
    Route::post('/nora/recordatorios/vencidos', [NoraReminderController::class, 'due'])->name('nora-reminders.due');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/manager', [ManagerController::class, 'index'])->name('manager.index');
    Route::post('/manager/inventario', [ManagerController::class, 'storeInventory'])->name('manager.inventory.store');
    Route::patch('/manager/inventario/{inventoryItem}/ajustar', [ManagerController::class, 'adjustInventory'])->name('manager.inventory.adjust');
});

Route::get('/consola', function (Request $request, ClinicResolver $clinics, ScheduleOptimizationService $optimizer, DailyBriefingService $briefings) {
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
    $isSelectedToday = $selectedDate === $now->toDateString() && $selectedRange === 'day';
    $consoleUrl = fn (\Illuminate\Support\Carbon $date, string $view): string => url('/consola').'?date='.$date->toDateString().'&view='.$view;
    $miniCalendarStart = $selectedDay->copy()->startOfMonth()->startOfWeek();
    $miniCalendarDays = collect(range(0, 41))->map(
        fn (int $day) => $miniCalendarStart->copy()->addDays($day)
    );
    $calendarPreviousMonthUrl = $consoleUrl($selectedDay->copy()->subMonthNoOverflow(), $selectedRange);
    $calendarNextMonthUrl = $consoleUrl($selectedDay->copy()->addMonthNoOverflow(), $selectedRange);
    $todayStart = $rangeStart->copy()->timezone(config('app.timezone'));
    $todayEnd = $rangeEnd->copy()->timezone(config('app.timezone'));
    $activeCallStatuses = ['queued', 'initiated', 'ringing', 'in-progress', 'answered'];

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

    $expectedRevenueCents = $todayAppointments->sum(
        fn (Appointment $appointment): int => (int) ($appointment->service?->price_cents ?? 0)
    );
    $pricedAppointmentsCount = $todayAppointments->filter(
        fn (Appointment $appointment): bool => $appointment->service?->price_cents !== null
    )->count();
    $employeeRevenueBreakdown = $clinic->stylists()
        ->where('is_active', true)
        ->where('is_internal', false)
        ->orderBy('name')
        ->get()
        ->map(function ($stylist) use ($todayAppointments): array {
            $appointments = $todayAppointments->where('stylist_id', $stylist->id);

            return [
                'id' => $stylist->id,
                'name' => $stylist->name,
                'revenue_cents' => $appointments->sum(
                    fn (Appointment $appointment): int => (int) ($appointment->service?->price_cents ?? 0)
                ),
                'services_count' => $appointments->filter(
                    fn (Appointment $appointment): bool => $appointment->service?->price_cents !== null
                )->count(),
            ];
        });

    $nextAppointment = $todayAppointments
        ->first(fn (Appointment $appointment): bool => $appointment->starts_at->greaterThanOrEqualTo($now));
    $scheduleOptimization = $selectedRange === 'day'
        ? $optimizer->suggestion($clinic, $selectedDay, $todayAppointments)
        : null;
    $scheduleOptimizationHistory = $selectedRange === 'day'
        ? $optimizer->recentOffers($clinic, $selectedDay)
        : collect();

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

    $reminderCallAppointments = $clinic->notificationEnabled('appointment_reminder_call')
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
            ->values()
        : collect();
    $nextReminderCall = $reminderCallAppointments->first();

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
        ->where(function ($query) use ($activeCallStatuses): void {
            $query->whereIn('status', $activeCallStatuses)
                ->orWhere('status', 'received');
        })
        ->where('created_at', '>=', now()->subSeconds(15))
        ->count()
        + DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('channel', 'voice')
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->whereIn('status', ['queued', 'initiated', 'ringing'])
                        ->where('created_at', '>=', now()->subMinutes(2));
                })->orWhere(function ($connected): void {
                    $connected->whereIn('status', ['in-progress', 'answered'])
                        ->where('created_at', '>=', now()->subMinutes(10));
                });
            })
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
        ->leftJoin('client_preferences', 'clients.id', '=', 'client_preferences.client_id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'voice')
        ->whereBetween('notifications.created_at', [$todayStart, $todayEnd])
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'clients.phone as client_phone',
            'clients.email as client_email',
            'clients.address as client_address',
            'clients.notification_preference',
            'clients.notes as client_notes',
            'client_preferences.hair_type',
            'client_preferences.preferred_stylist',
            'client_preferences.color_formula',
            'client_preferences.allergies',
            'client_preferences.beauty_notes',
            'appointments.starts_at as appointment_starts_at',
            'appointments.ends_at as appointment_ends_at',
            'appointments.status as appointment_status',
            'appointments.priority as appointment_priority',
            'appointments.source as appointment_source',
            'appointments.reason as appointment_reason',
            'appointments.chair_station',
            'appointments.deposit_cents',
            'appointments.client_comments',
            'appointments.internal_notes',
            'services.name as service_name',
            'services.duration_minutes',
            'services.price_cents',
            'stylists.name as stylist_name',
            'stylists.email as stylist_email',
            'stylists.phone as stylist_phone',
            'stylists.specialty as stylist_specialty',
        )
        ->orderByDesc('notifications.created_at')
        ->get();

    $activeInboundCall = DB::table('call_logs')
        ->leftJoin('clients', 'call_logs.client_id', '=', 'clients.id')
        ->leftJoin('appointments', 'call_logs.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('call_logs.clinic_id', $clinic->id)
        ->where(function ($query): void {
            $query->whereNull('call_logs.intent')
                ->orWhere('call_logs.intent', 'appointment_lookup');
        })
        ->where(function ($query): void {
            $query->where(function ($active): void {
                $active->whereIn('call_logs.status', ['in-progress', 'answered'])
                    ->where('call_logs.created_at', '>=', now()->subHours(6));
            })->orWhere(function ($ringing): void {
                $ringing->whereIn('call_logs.status', ['queued', 'initiated', 'ringing', 'received'])
                    ->where('call_logs.created_at', '>=', now()->subMinutes(2));
            });
        })
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
        ->where(function ($query): void {
            $query->where(function ($pending): void {
                $pending->whereIn('notifications.status', ['queued', 'initiated', 'ringing'])
                    ->where('notifications.created_at', '>=', now()->subMinutes(2));
            })->orWhere(function ($connected): void {
                $connected->whereIn('notifications.status', ['in-progress', 'answered'])
                    ->where('notifications.created_at', '>=', now()->subMinutes(10));
            });
        })
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
        ->leftJoin('client_preferences', 'clients.id', '=', 'client_preferences.client_id')
        ->leftJoin('appointments', 'call_logs.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('call_logs.clinic_id', $clinic->id)
        ->whereBetween('call_logs.created_at', [$todayStart, $todayEnd])
        ->select(
            'call_logs.*',
            'clients.first_name',
            'clients.last_name',
            'clients.phone as client_phone',
            'clients.email as client_email',
            'clients.address as client_address',
            'clients.notification_preference',
            'clients.notes as client_notes',
            'client_preferences.hair_type',
            'client_preferences.preferred_stylist',
            'client_preferences.color_formula',
            'client_preferences.allergies',
            'client_preferences.beauty_notes',
            'appointments.starts_at as appointment_starts_at',
            'appointments.ends_at as appointment_ends_at',
            'appointments.status as appointment_status',
            'appointments.priority as appointment_priority',
            'appointments.source as appointment_source',
            'appointments.reason as appointment_reason',
            'appointments.chair_station',
            'appointments.deposit_cents',
            'appointments.client_comments',
            'appointments.internal_notes',
            'services.name as service_name',
            'services.duration_minutes',
            'services.price_cents',
            'stylists.name as stylist_name',
            'stylists.email as stylist_email',
            'stylists.phone as stylist_phone',
            'stylists.specialty as stylist_specialty',
        )
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
        ->leftJoin('client_preferences', 'clients.id', '=', 'client_preferences.client_id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'sms')
        ->whereBetween('notifications.created_at', [$todayStart, $todayEnd])
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'clients.phone as client_phone',
            'clients.email as client_email',
            'clients.address as client_address',
            'clients.notification_preference',
            'clients.notes as client_notes',
            'client_preferences.hair_type',
            'client_preferences.preferred_stylist',
            'client_preferences.color_formula',
            'client_preferences.allergies',
            'client_preferences.beauty_notes',
            'appointments.starts_at as appointment_starts_at',
            'appointments.ends_at as appointment_ends_at',
            'appointments.status as appointment_status',
            'appointments.priority as appointment_priority',
            'appointments.source as appointment_source',
            'appointments.reason as appointment_reason',
            'appointments.chair_station',
            'appointments.deposit_cents',
            'appointments.client_comments',
            'appointments.internal_notes',
            'services.name as service_name',
            'services.duration_minutes',
            'services.price_cents',
            'stylists.name as stylist_name',
            'stylists.email as stylist_email',
            'stylists.phone as stylist_phone',
            'stylists.specialty as stylist_specialty',
        )
        ->orderByDesc('notifications.created_at')
        ->get();

    $emailDetails = DB::table('notifications')
        ->leftJoin('clients', 'notifications.client_id', '=', 'clients.id')
        ->leftJoin('client_preferences', 'clients.id', '=', 'client_preferences.client_id')
        ->leftJoin('appointments', 'notifications.appointment_id', '=', 'appointments.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('stylists', 'appointments.stylist_id', '=', 'stylists.id')
        ->where('notifications.clinic_id', $clinic->id)
        ->where('notifications.channel', 'email')
        ->whereBetween('notifications.created_at', [$todayStart, $todayEnd])
        ->select(
            'notifications.*',
            'clients.first_name',
            'clients.last_name',
            'clients.phone as client_phone',
            'clients.email as client_email',
            'clients.address as client_address',
            'clients.notification_preference',
            'clients.notes as client_notes',
            'client_preferences.hair_type',
            'client_preferences.preferred_stylist',
            'client_preferences.color_formula',
            'client_preferences.allergies',
            'client_preferences.beauty_notes',
            'appointments.starts_at as appointment_starts_at',
            'appointments.ends_at as appointment_ends_at',
            'appointments.status as appointment_status',
            'appointments.priority as appointment_priority',
            'appointments.source as appointment_source',
            'appointments.reason as appointment_reason',
            'appointments.chair_station',
            'appointments.deposit_cents',
            'appointments.client_comments',
            'appointments.internal_notes',
            'services.name as service_name',
            'services.duration_minutes',
            'services.price_cents',
            'stylists.name as stylist_name',
            'stylists.email as stylist_email',
            'stylists.phone as stylist_phone',
            'stylists.specialty as stylist_specialty',
        )
        ->orderByDesc('notifications.created_at')
        ->get();

    $communicationAppointmentIds = $latestCalls
        ->concat($smsDetails)
        ->concat($emailDetails)
        ->pluck('appointment_id')
        ->filter()
        ->unique()
        ->values();

    $clientResponses = $communicationAppointmentIds->isEmpty()
        ? collect()
        : DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_client_response')
            ->whereIn('appointment_id', $communicationAppointmentIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('appointment_id')
            ->map(fn ($responses) => $responses->first());

    $latestCalls
        ->concat($smsDetails)
        ->concat($emailDetails)
        ->each(function (object $communication) use ($clientResponses): void {
            $response = $clientResponses->get($communication->appointment_id ?? null);
            $communication->client_response = $response?->body;
            $communication->client_responded_at = $response?->created_at;
        });

    $dailyBriefing = $briefings->forToday($clinic, $request->user());

    return view('console.index', [
        'clinic' => $clinic,
        'timezone' => $timezone,
        'now' => $now,
        'selectedDate' => $selectedDate,
        'selectedRange' => $selectedRange,
        'selectedDateLabel' => $selectedDateLabel,
        'isSelectedToday' => $isSelectedToday,
        'selectedDay' => $selectedDay,
        'rangeStart' => $rangeStart,
        'rangeEnd' => $rangeEnd,
        'miniCalendarDays' => $miniCalendarDays,
        'calendarPreviousMonthUrl' => $calendarPreviousMonthUrl,
        'calendarNextMonthUrl' => $calendarNextMonthUrl,
        'todayAppointments' => $todayAppointments,
        'expectedRevenueCents' => $expectedRevenueCents,
        'pricedAppointmentsCount' => $pricedAppointmentsCount,
        'employeeRevenueBreakdown' => $employeeRevenueBreakdown,
        'nextAppointment' => $nextAppointment,
        'scheduleOptimization' => $scheduleOptimization,
        'scheduleOptimizationHistory' => $scheduleOptimizationHistory,
        'nextReminderCall' => $nextReminderCall,
        'reminderCallAppointments' => $reminderCallAppointments,
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
        'dailyBriefing' => $dailyBriefing,
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
        'notification_preferences' => array_merge($clinic->notification_preferences ?? [], $preferences),
    ])->save();

    return redirect('/ajustes#notificaciones')->with('settings_status', 'Preferencias de notificaciones actualizadas.');
})->middleware('auth');

Route::post('/ajustes/desvio-llamadas/instrucciones', function (Request $request, ClinicResolver $clinics, TwilioSmsService $sms, CallForwardingInstructionService $instructions) {
    $clinic = $clinics->currentOrCreate($request->user());
    $data = $request->validate([
        'mode' => ['required', Rule::in(CallForwardingInstructionService::MODES)],
        'ring_seconds' => ['nullable', 'integer', Rule::in([15, 20, 25, 30])],
        'recipient' => ['required', 'string', 'max:40'],
        'operator' => ['required', 'string', 'max:30'],
    ]);

    if (! $clinic->twilio_phone_number) {
        return redirect('/ajustes#servicios')->with('settings_error', 'Primero necesitas tener asignado el numero de Secretary365.');
    }

    $seconds = (int) ($data['ring_seconds'] ?? 20);
    $country = $instructions->countryForPhone($clinic->phone, $clinic->country_code);
    if (! array_key_exists($data['operator'], $instructions->operators($country))) {
        return redirect('/ajustes#servicios')->withErrors(['operator' => 'Selecciona un operador válido para el país del salón.']);
    }
    $body = $instructions->message($data['mode'], $clinic->twilio_phone_number, $seconds, $country, $data['operator']);
    $providerMessageId = null;
    $status = 'sent';
    $error = null;

    try {
        $providerMessageId = $sms->send($data['recipient'], $body);
        if (! $providerMessageId) {
            $status = 'failed';
            $error = 'Twilio SMS no esta configurado o el movil no es valido.';
        }
    } catch (\Throwable $exception) {
        $status = 'failed';
        $error = $exception->getMessage();
    }

    DB::table('notifications')->insert([
        'clinic_id' => $clinic->id,
        'channel' => 'sms',
        'event' => 'call_forwarding_instructions',
        'recipient' => $data['recipient'],
        'status' => $status,
        'provider_message_id' => $providerMessageId,
        'body' => $body,
        'error' => $error,
        'sent_at' => $status === 'sent' ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($status !== 'sent') {
        return redirect('/ajustes#servicios')->with('settings_error', 'No pudimos enviar las instrucciones: '.$error);
    }

    $clinic->forceFill(['notification_preferences' => array_merge($clinic->notification_preferences ?? [], [
        'call_forwarding_mode' => $data['mode'],
        'call_forwarding_ring_seconds' => $seconds,
        'call_forwarding_recipient' => $data['recipient'],
        'call_forwarding_country' => $country,
        'call_forwarding_operator' => $data['operator'],
        'call_forwarding_instructions_sent_at' => now()->toIso8601String(),
    ])])->save();

    return redirect('/ajustes#servicios')->with('settings_status', 'Instrucciones enviadas por SMS: '.$instructions->label($data['mode'], $seconds).'.');
})->middleware('auth')->name('settings.call-forwarding-instructions');

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
    Route::get('/costos-salones', [SuperAdminCostController::class, 'index'])->name('super-admin.costs');
    Route::get('/gestion-usuarios', [UserAdminController::class, 'index'])->name('users.index');
    Route::post('/gestion-usuarios', [UserAdminController::class, 'store'])->name('users.store');
    Route::patch('/gestion-usuarios/{user}/estado', [UserAdminController::class, 'status'])->name('users.status');
    Route::delete('/gestion-usuarios/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
    Route::get('/base-de-datos/{table?}', [DatabaseAdminController::class, 'index'])->name('database.index');
    Route::post('/base-de-datos/{table}', [DatabaseAdminController::class, 'store'])->name('database.store');
    Route::post('/base-de-datos/{table}/eliminar-seleccionados', [DatabaseAdminController::class, 'bulkDestroy'])->name('database.bulk-destroy');
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
Route::post('/twilio/voice/incoming/fallback', [TwilioPhoneNumberController::class, 'incomingFallback'])->name('twilio.voice.incoming-fallback');
Route::post('/twilio/voice/browser/status', [TwilioPhoneNumberController::class, 'browserStatus'])->name('twilio.voice.browser-status');
Route::post('/twilio/voice/incoming/complete', [TwilioPhoneNumberController::class, 'incomingComplete'])->name('twilio.voice.incoming-complete');
Route::post('/twilio/voice/reminder/{appointment}/{token}', [TwilioPhoneNumberController::class, 'reminder'])->name('twilio.voice.reminder');
Route::post('/twilio/voice/reminder/{appointment}/{token}/choice', [TwilioPhoneNumberController::class, 'reminderChoice'])->name('twilio.voice.reminder-choice');
Route::post('/twilio/voice/reminder/status', [TwilioPhoneNumberController::class, 'reminderStatus'])->name('twilio.voice.reminder-status');
Route::post('/twilio/voice/client-call/{client}/{token}', [NoraClientCallController::class, 'twiml'])->name('twilio.voice.client-call');
Route::post('/twilio/voice/client-call/status', [NoraClientCallController::class, 'status'])->name('twilio.voice.client-call-status');
Route::get('/twilio/voice/browser/token', [TwilioPhoneNumberController::class, 'browserToken'])->middleware('auth')->name('twilio.voice.browser-token');
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
    Route::post('/google-calendar/detect', [GoogleCalendarController::class, 'detect'])->name('google-calendar.detect');
    Route::post('/google-calendar/organizar', [GoogleCalendarController::class, 'organize'])->name('google-calendar.organize');
    Route::put('/google-calendar/mappings', [GoogleCalendarController::class, 'updateMappings'])->name('google-calendar.mappings.update');
    Route::get('/voz-secretaria/prueba', [GoogleTextToSpeechController::class, 'preview'])->name('secretary-voice.preview');
    Route::post('/voz-secretaria/probar-llamada', [GoogleTextToSpeechController::class, 'previewCall'])->name('secretary-voice.preview-call');
    Route::post('/voz-secretaria/activar', [GoogleTextToSpeechController::class, 'activate'])->name('secretary-voice.activate');
    Route::post('/twilio/numeros/asignar', [TwilioPhoneNumberController::class, 'assign'])->name('twilio-number.assign');
});
