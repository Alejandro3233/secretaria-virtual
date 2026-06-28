<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Carbon;

class ResourceAvailabilityService
{
    public function available(?Service $service, Carbon $startsAt, Carbon $endsAt, ?int $ignoreAppointmentId = null): bool
    {
        $resource = $service?->facilityResource;
        if (! $resource || ! $resource->is_active) return true;

        $used = Appointment::query()
            ->with('service')
            ->where('clinic_id', $service->clinic_id)
            ->when($ignoreAppointmentId, fn ($query) => $query->where('id', '!=', $ignoreAppointmentId))
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereHas('service', fn ($query) => $query->where('facility_resource_id', $resource->id))
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfDay()->timezone(config('app.timezone')),
                $startsAt->copy()->endOfDay()->timezone(config('app.timezone')),
            ])->get()
            ->filter(function (Appointment $appointment) use ($startsAt, $endsAt): bool {
                $appointmentStart = $appointment->starts_at->copy()->timezone($startsAt->getTimezone());
                $appointmentEnd = ($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes($appointment->service?->duration_minutes ?? 60))
                    ->copy()->timezone($startsAt->getTimezone());
                return $appointmentStart->lessThan($endsAt) && $appointmentEnd->greaterThan($startsAt);
            })->sum(fn (Appointment $appointment) => max(1, (int) ($appointment->service?->resource_units ?? 1)));

        return $used + max(1, (int) $service->resource_units) <= $resource->capacity;
    }

    public function validationMessage(?Service $service, Carbon $startsAt, Carbon $endsAt, ?int $ignoreAppointmentId = null): ?string
    {
        if ($this->available($service, $startsAt, $endsAt, $ignoreAppointmentId)) return null;
        return 'No hay disponibilidad de '.($service?->facilityResource?->name ?? 'equipamiento').' para ese horario.';
    }
}
