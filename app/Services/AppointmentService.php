<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;

class AppointmentService
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendar,
        private readonly AppointmentNotificationService $notifications,
    )
    {
    }

    public function create(Clinic $clinic, array $data): Appointment
    {
        $appointment = $clinic->appointments()->create(array_merge($data, [
            'google_sync_status' => 'pending',
        ]));

        $this->syncIfConnected($appointment);
        $this->notifications->appointmentCreated($appointment);

        return $appointment;
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        $appointment->fill(array_merge($data, [
            'google_sync_status' => 'pending',
        ]));
        $appointment->save();

        $this->syncIfConnected($appointment);

        return $appointment;
    }

    public function cancel(Appointment $appointment): Appointment
    {
        $appointment->forceFill([
            'status' => 'cancelled',
            'google_sync_status' => 'pending',
        ])->save();

        $this->syncIfConnected($appointment);

        return $appointment;
    }

    private function syncIfConnected(Appointment $appointment): void
    {
        $clinic = $appointment->clinic;

        if (! $clinic?->google_connected_at || ! $clinic->google_refresh_token) {
            return;
        }

        $this->googleCalendar->upsertAppointment($appointment);
    }
}
