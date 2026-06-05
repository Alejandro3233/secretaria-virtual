<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\ClinicResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());

        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->orderBy('starts_at')
            ->get();

        return view('appointments.index', [
            'clinic' => $clinic,
            'appointments' => $appointments,
        ]);
    }

    public function edit(Request $request, Appointment $appointment): View
    {
        $clinic = $this->appointmentClinic($request, $appointment);

        return view('appointments.edit', [
            'appointment' => $appointment->load(['client', 'service', 'stylist']),
            'services' => $clinic->services()->where('is_active', true)->orderBy('name')->get(),
            'stylists' => $clinic->stylists()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->appointmentClinic($request, $appointment);

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

        $startsAt = Carbon::parse($data['starts_at']);

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
            return redirect('/citas')->with('appointment_error', $exception->getMessage());
        }

        return redirect('/citas')->with('appointment_status', 'Cita eliminada correctamente.');
    }

    private function appointmentClinic(Request $request, Appointment $appointment)
    {
        $clinic = $request->user()->primaryClinic();

        abort_unless($clinic && $appointment->clinic_id === $clinic->id, 404);

        return $clinic;
    }
}
