<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\ClinicResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $timezone = $clinic->localTimezone();
        $period = $request->query('period', 'all');

        if (! in_array($period, ['all', 'week', 'month'], true)) {
            $period = 'all';
        }

        $appointmentsQuery = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id);

        if ($period === 'week') {
            $appointmentsQuery->whereBetween('starts_at', [
                now($timezone)->startOfWeek()->timezone(config('app.timezone')),
                now($timezone)->endOfWeek()->timezone(config('app.timezone')),
            ]);
        }

        if ($period === 'month') {
            $appointmentsQuery->whereBetween('starts_at', [
                now($timezone)->startOfMonth()->timezone(config('app.timezone')),
                now($timezone)->endOfMonth()->timezone(config('app.timezone')),
            ]);
        }

        $appointments = $appointmentsQuery
            ->orderByDesc('starts_at')
            ->get()
            ->each(function (Appointment $appointment) use ($timezone): void {
                $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
                $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
            });

        return view('appointments.index', [
            'clinic' => $clinic,
            'appointments' => $appointments,
            'period' => $period,
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

    public function update(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
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

        try {
            $appointments->update($appointment, [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes((int) $data['duration_minutes']),
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
