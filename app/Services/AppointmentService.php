<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use Carbon\CarbonInterface;

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
        $appointment = $clinic->appointments()->create(array_merge($this->normalizeAppointmentDates($data), [
            'reminder_call_enabled' => false,
            'reminder_sms_enabled' => false,
            'google_sync_status' => 'pending',
        ]));

        $this->syncIfConnected($appointment);
        $this->notifications->appointmentCreated($appointment);

        return $appointment;
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        $notifyOnChange = ['starts_at', 'ends_at', 'stylist_id', 'service_id', 'status'];

        $appointment->fill(array_merge($this->normalizeAppointmentDates($data), [
            'google_sync_status' => 'pending',
        ]));
        $shouldNotify = $appointment->isDirty($notifyOnChange);
        $appointment->save();

        $this->syncIfConnected($appointment);

        if ($shouldNotify && ! in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            $this->notifications->appointmentUpdated($appointment);
        }

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

    public function confirm(Appointment $appointment): Appointment
    {
        $previousStatus = $appointment->status;

        $appointment->forceFill([
            'status' => 'confirmed',
            'google_sync_status' => 'pending',
        ])->save();

        $this->syncIfConnected($appointment);

        if (in_array($previousStatus, ['cancelled', 'canceled'], true)) {
            $this->notifications->appointmentReconfirmed($appointment);
        }

        return $appointment;
    }

    private function normalizeAppointmentDates(array $data): array
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if (($data[$field] ?? null) instanceof CarbonInterface) {
                $data[$field] = $data[$field]->copy()->timezone(config('app.timezone'));
            }
        }

        return $data;
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
