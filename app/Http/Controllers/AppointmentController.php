<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\AppointmentReminderCallService;
use App\Services\ClinicResolver;
use App\Services\StylistScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $timezone = $clinic->localTimezone();
        $period = $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $selectedDate = $request->filled('date')
            ? Carbon::parse((string) $request->query('date'), $timezone)
            : now($timezone);

        $appointmentsQuery = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id);

        if ($period === 'day') {
            $appointmentsQuery->whereBetween('starts_at', [
                $selectedDate->copy()->startOfDay()->timezone(config('app.timezone')),
                $selectedDate->copy()->endOfDay()->timezone(config('app.timezone')),
            ]);
        }

        if ($period === 'week') {
            $appointmentsQuery->whereBetween('starts_at', [
                $selectedDate->copy()->startOfWeek()->timezone(config('app.timezone')),
                $selectedDate->copy()->endOfWeek()->timezone(config('app.timezone')),
            ]);
        }

        if ($period === 'month') {
            $appointmentsQuery->whereBetween('starts_at', [
                $selectedDate->copy()->startOfMonth()->timezone(config('app.timezone')),
                $selectedDate->copy()->endOfMonth()->timezone(config('app.timezone')),
            ]);
        }

        $appointments = $appointmentsQuery
            ->orderByDesc('starts_at')
            ->get()
            ->each(function (Appointment $appointment) use ($timezone): void {
                $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
                $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
            });

        $notificationStates = DB::table('notifications')
            ->whereIn('appointment_id', $appointments->pluck('id'))
            ->where(function ($query): void {
                $query->where(function ($sent): void {
                    $sent->whereIn('channel', ['sms', 'email'])
                        ->whereIn('status', ['sent', 'queued']);
                })
                    ->orWhere('event', 'appointment_client_response')
                    ->orWhere('event', 'appointment_reminder_call');
            })
            ->get()
            ->groupBy('appointment_id');

        $appointments->each(function (Appointment $appointment) use ($notificationStates): void {
            $notifications = $notificationStates->get($appointment->id, collect());
            $appointment->setAttribute('notification_sent', $notifications->contains(
                fn ($notification) => in_array($notification->channel, ['sms', 'email'], true)
                    && in_array($notification->status, ['sent', 'queued'], true)
            ));
            $appointment->setAttribute('client_responded', $notifications->contains('event', 'appointment_client_response'));
            $appointment->setAttribute('client_cancelled', $notifications->contains(
                fn ($notification) => $notification->event === 'appointment_client_response'
                    && $notification->body === 'cancel'
            ));
            $appointment->setAttribute(
                'reminder_call_status',
                $notifications
                    ->where('event', 'appointment_reminder_call')
                    ->sortByDesc('created_at')
                    ->first()?->status,
            );
        });

        return view('appointments.index', [
            'clinic' => $clinic,
            'appointments' => $appointments,
            'period' => $period,
            'selectedDate' => $selectedDate,
            'timezone' => $timezone,
        ]);
    }

    public function edit(Request $request, Appointment $appointment): View
    {
        $clinic = $this->appointmentClinic($request, $appointment);
        $timezone = $clinic->localTimezone();
        $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
        $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);

        return view('appointments.edit', [
            'appointment' => $appointment->load(['client', 'service', 'stylist']),
            'services' => $clinic->services()->where('is_active', true)->orderBy('name')->get(),
            'stylists' => $clinic->stylists()->where('is_active', true)->orderBy('name')->get(),
            'timezone' => $timezone,
        ]);
    }

    public function update(Request $request, Appointment $appointment, AppointmentService $appointments, StylistScheduleService $schedules): RedirectResponse
    {
        $clinic = $this->appointmentClinic($request, $appointment);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled,canceled'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'stylist_id' => ['nullable', 'integer', 'exists:stylists,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'chair_station' => ['nullable', 'string', 'max:255'],
            'client_comments' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $startsAt = Carbon::parse($data['starts_at'], $clinic->localTimezone());
        $endsAt = $startsAt->copy()->addMinutes((int) $data['duration_minutes']);
        $stylist = ! empty($data['stylist_id'])
            ? $clinic->stylists()->whereKey($data['stylist_id'])->firstOrFail()
            : null;

        if ($stylist && ($scheduleError = $schedules->validationMessage($stylist, $startsAt, $endsAt))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => $scheduleError]);
        }

        try {
            $appointments->update($appointment, [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $data['status'],
                'service_id' => $data['service_id'] ?? null,
                'stylist_id' => $data['stylist_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'chair_station' => $data['chair_station'] ?? null,
                'client_comments' => $data['client_comments'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->with('appointment_error', $exception->getMessage());
        }

        return redirect('/citas')->with('appointment_status', 'Cita actualizada correctamente.');
    }

    public function destroy(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->appointmentClinic($request, $appointment);

        try {
            if ($appointment->google_calendar_event_id) {
                $appointments->cancel($appointment);
            }

            $appointment->delete();
        } catch (\Throwable $exception) {
            return redirect('/citas')->with('appointment_error', 'No se pudo eliminar la cita. Revisa la conexion con Google Calendar e intentalo de nuevo.');
        }

        return redirect('/citas')->with('appointment_status', 'Cita eliminada correctamente.');
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->appointmentClinic($request, $appointment);

        if (in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            return redirect('/citas')->with('appointment_status', 'La cita ya estaba cancelada.');
        }

        try {
            $appointments->cancel($appointment);
        } catch (\Throwable $exception) {
            return back()->with('appointment_error', 'No se pudo cancelar la cita. Revisa la conexion con Google Calendar e intentalo de nuevo.');
        }

        return redirect('/citas')->with('appointment_status', 'Cita cancelada correctamente.');
    }

    public function reminders(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointmentClinic($request, $appointment);

        if (! $this->ensureReminderColumns()) {
            return back()->with('appointment_error', 'Falta actualizar la base de datos para guardar recordatorios por cita.');
        }

        $data = $request->validate([
            'reminder_call_enabled' => ['required', 'boolean'],
            'reminder_sms_enabled' => ['required', 'boolean'],
        ]);

        $appointment->update([
            'reminder_call_enabled' => (bool) $data['reminder_call_enabled'],
            'reminder_sms_enabled' => (bool) $data['reminder_sms_enabled'],
        ]);

        return back()->with('appointment_status', 'Preferencia de recordatorio actualizada.');
    }

    public function callNow(Request $request, Appointment $appointment, AppointmentReminderCallService $reminders): RedirectResponse
    {
        $this->appointmentClinic($request, $appointment);
        $appointment->load(['clinic', 'client']);

        if (! $appointment->reminder_call_enabled || in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            return back()->with('call_error', 'Esta cita no tiene activada la llamada de Nora.');
        }

        $activeCall = DB::table('notifications')
            ->where('appointment_id', $appointment->id)
            ->where('event', 'appointment_reminder_call')
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->whereIn('status', ['queued', 'initiated', 'ringing'])
                        ->where('created_at', '>=', now()->subMinutes(2));
                })->orWhere(function ($connected): void {
                    $connected->where('status', 'in-progress')
                        ->where('created_at', '>=', now()->subMinutes(10));
                });
            })
            ->exists();

        if ($activeCall) return back()->with('call_error', 'Nora ya está llamando o tiene esta llamada en proceso.');

        return $reminders->call($appointment)
            ? back()->with('call_status', 'Nora comenzó la llamada de recordatorio al cliente.')
            : back()->with('call_error', 'No se pudo iniciar la llamada. Revisa el teléfono del cliente y la configuración de Twilio.');
    }

    private function ensureReminderColumns(): bool
    {
        try {
            if (! Schema::hasColumn('appointments', 'reminder_call_enabled')) {
                Schema::table('appointments', function (Blueprint $table): void {
                    $table->boolean('reminder_call_enabled')->default(false);
                });
            }

            if (! Schema::hasColumn('appointments', 'reminder_sms_enabled')) {
                Schema::table('appointments', function (Blueprint $table): void {
                    $table->boolean('reminder_sms_enabled')->default(false);
                });
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function appointmentClinic(Request $request, Appointment $appointment)
    {
        $clinic = $request->user()->primaryClinic();

        abort_unless($clinic && $appointment->clinic_id === $clinic->id, 404);

        return $clinic;
    }
}
