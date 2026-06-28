<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Stylist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleOptimizationService
{
    public function recentOffers(Clinic $clinic, Carbon $date): Collection
    {
        $timezone = $clinic->localTimezone();
        $acceptedIds = DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_optimization_accepted')
            ->pluck('appointment_id');
        $appointments = Appointment::with(['client', 'stylist'])->where('clinic_id', $clinic->id)->get()->keyBy('id');
        $stylists = $clinic->stylists()->get()->keyBy('id');

        return DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_optimization_offer')
            ->whereIn('status', ['sent', 'queued'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(function (object $offer) use ($appointments, $stylists, $acceptedIds, $timezone): ?array {
                $body = json_decode((string) $offer->body, true);
                $appointment = $appointments->get($offer->appointment_id);
                if (! $appointment || empty($body['proposed_start'])) return null;

                $proposedStart = Carbon::parse($body['proposed_start'])->timezone($timezone);
                $stylist = $stylists->get((int) ($body['proposed_stylist_id'] ?? $appointment->stylist_id));

                return [
                    'appointment' => $appointment,
                    'client_name' => trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')) ?: 'Cliente',
                    'original_start' => ! empty($body['original_start'])
                        ? Carbon::parse($body['original_start'])->timezone($timezone)
                        : $appointment->starts_at->copy()->timezone($timezone),
                    'proposed_start' => $proposedStart,
                    'stylist' => $stylist,
                    'accepted' => $acceptedIds->contains($appointment->id),
                    'sent_at' => Carbon::parse($offer->created_at)->timezone($timezone),
                ];
            })
            ->filter(fn (?array $offer) => $offer && $offer['proposed_start']->isSameDay($date))
            ->values();
    }

    public function alternativeFor(Appointment $appointment): ?array
    {
        $appointment->loadMissing(['clinic', 'client', 'service', 'stylist']);
        if (! $appointment->clinic) return null;

        $clinic = $appointment->clinic;
        $timezone = $clinic->localTimezone();
        $date = $this->startOf($appointment, $timezone)->startOfDay();
        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [
                $date->copy()->startOfDay()->timezone(config('app.timezone')),
                $date->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->get();
        $appointments = $appointments->concat($this->pendingReservations($clinic, $appointments, $appointment->id));
        $freshCandidate = $appointments->firstWhere('id', $appointment->id) ?? $appointment;

        return $this->suggestionForCandidate($clinic, $date, $appointments, $freshCandidate);
    }

    public function suggestion(Clinic $clinic, Carbon $date, Collection $appointments): ?array
    {
        $timezone = $clinic->localTimezone();
        $now = now($timezone);
        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [
                $date->copy()->startOfDay()->timezone(config('app.timezone')),
                $date->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->get();
        $realAppointments = $appointments;
        $pendingOfferAppointmentIds = $this->pendingOfferAppointmentIds($clinic);
        $appointments = $appointments->concat($this->pendingReservations($clinic, $realAppointments));

        $fivePm = $date->copy()->setTime(17, 0);
        $candidates = $realAppointments
            ->reject(fn (Appointment $appointment) => $pendingOfferAppointmentIds->contains($appointment->id))
            ->filter(function (Appointment $appointment) use ($now, $fivePm, $timezone): bool {
                $phone = (string) ($appointment->client?->phone ?? '');
                $start = $this->startOf($appointment, $timezone);

                return $start->greaterThanOrEqualTo($fivePm)
                    && $start->greaterThan($now)
                    && $phone !== ''
                    && ! str_starts_with($phone, 'google:');
            })
            ->sortByDesc(fn (Appointment $appointment) => $this->startOf($appointment, $timezone)->timestamp);

        foreach ($candidates as $candidate) {
            $suggestion = $this->suggestionForCandidate($clinic, $date, $appointments, $candidate);
            if ($suggestion) return $suggestion;
        }

        return null;
    }

    private function suggestionForCandidate(Clinic $clinic, Carbon $date, Collection $appointments, Appointment $candidate): ?array
    {
        $timezone = $clinic->localTimezone();
        $now = now($timezone);
        $stylists = $clinic->stylists()
            ->where('is_active', true)
            ->where('is_internal', false)
            ->when($candidate->service_id, fn ($query) => $query->where(function ($services) use ($candidate): void {
                $services->where('service_id', $candidate->service_id)
                    ->orWhereHas('services', fn ($assigned) => $assigned->whereKey($candidate->service_id));
            }))
            ->get()
            ->sortBy(fn (Stylist $stylist) => $stylist->id === $candidate->stylist_id ? 0 : 1);

        foreach ($stylists as $stylist) {
            $daySchedule = $stylist->scheduleForDate($date);
            if (! $daySchedule) continue;

            $workStart = $this->atTime($date, $daySchedule['start']);
            $workEnd = $this->atTime($date, $daySchedule['end']);
            if ($date->isSameDay($now) && $now->greaterThan($workStart)) $workStart = $now->copy()->ceilMinutes(5);
            $scheduled = $appointments
                ->where('stylist_id', $stylist->id)
                ->sortBy(fn (Appointment $item) => $this->startOf($item, $timezone)->timestamp)
                ->values();

            $suggestion = $this->earliestGapForCandidate($clinic, $stylist, $scheduled, $candidate, $workStart, $workEnd);
            if ($suggestion) return $suggestion;
        }

        return null;
    }

    private function earliestGapForCandidate(Clinic $clinic, Stylist $stylist, Collection $appointments, Appointment $candidate, Carbon $workStart, Carbon $workEnd): ?array
    {
        $timezone = $clinic->localTimezone();
        $candidateStart = $this->startOf($candidate, $timezone);
        $duration = $candidateStart->diffInMinutes($this->endOf($candidate, $timezone));
        $cursor = $workStart->copy();
        $earlierAppointments = $appointments
            ->reject(fn (Appointment $appointment) => $appointment->id === $candidate->id)
            ->filter(fn (Appointment $appointment) => $this->startOf($appointment, $timezone)->lessThan($candidateStart))
            ->sortBy(fn (Appointment $appointment) => $this->startOf($appointment, $timezone)->timestamp);

        foreach ($earlierAppointments as $appointment) {
            $appointmentStart = $this->startOf($appointment, $timezone);
            if ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($appointmentStart)) {
                return $this->buildSuggestion($clinic, $stylist, $candidate, $cursor, $duration);
            }

            $appointmentEnd = $this->endOf($appointment, $timezone);
            if ($appointmentEnd->greaterThan($cursor)) $cursor = $appointmentEnd;
        }

        if ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($candidateStart)
            && $cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($workEnd)) {
            return $this->buildSuggestion($clinic, $stylist, $candidate, $cursor, $duration);
        }

        return null;
    }

    private function buildSuggestion(Clinic $clinic, Stylist $stylist, Appointment $candidate, Carbon $gapStart, int $duration): ?array
    {
        $timezone = $clinic->localTimezone();
        $candidateStart = $this->startOf($candidate, $timezone);
        $proposedEnd = $gapStart->copy()->addMinutes($duration);
        if ($gapStart->diffInMinutes($candidateStart) < 30
            || $stylist->isOnBreak($gapStart, $proposedEnd)
            || $this->hasConflict($clinic->id, $stylist->id, $candidate->id, $gapStart, $proposedEnd)) {
            return null;
        }

        $alreadySent = DB::table('notifications')
            ->where('appointment_id', $candidate->id)
            ->where('event', 'appointment_optimization_offer')
            ->where('body', 'like', '%"proposed_start":"'.$gapStart->toIso8601String().'"%')
            ->where('body', 'like', '%"proposed_stylist_id":'.$stylist->id.'%')
            ->whereIn('status', ['sent', 'queued'])
            ->exists();

        return [
            'appointment' => $candidate,
            'stylist' => $stylist,
            'current_start' => $this->startOf($candidate, $timezone),
            'proposed_start' => $gapStart->copy(),
            'proposed_end' => $proposedEnd,
            'already_sent' => $alreadySent,
        ];
    }

    private function startOf(Appointment $appointment, string $timezone): Carbon
    {
        return $appointment->starts_at->copy()->timezone($timezone);
    }

    private function endOf(Appointment $appointment, string $timezone): Carbon
    {
        return ($appointment->ends_at
            ?? $appointment->starts_at->copy()->addMinutes($appointment->service?->duration_minutes ?? 60))
            ->copy()
            ->timezone($timezone);
    }

    private function atTime(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $date->copy()->setTime($hour, $minute);
    }

    private function hasConflict(int $clinicId, int $stylistId, int $excludedAppointmentId, Carbon $startsAt, Carbon $endsAt): bool
    {
        return Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('stylist_id', $stylistId)
            ->where('id', '!=', $excludedAppointmentId)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->where('starts_at', '<', $endsAt->copy()->timezone(config('app.timezone')))
            ->where('ends_at', '>', $startsAt->copy()->timezone(config('app.timezone')))
            ->exists();
    }

    private function pendingOfferAppointmentIds(Clinic $clinic): Collection
    {
        $acceptedIds = DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_optimization_accepted')
            ->pluck('appointment_id');

        return DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_optimization_offer')
            ->whereIn('status', ['sent', 'queued'])
            ->where('created_at', '>=', now()->subHours(48))
            ->whereNotIn('appointment_id', $acceptedIds)
            ->pluck('appointment_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function pendingReservations(Clinic $clinic, Collection $appointments, ?int $excludeAppointmentId = null): Collection
    {
        $acceptedIds = DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_optimization_accepted')
            ->pluck('appointment_id');

        return DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->where('event', 'appointment_optimization_offer')
            ->whereIn('status', ['sent', 'queued'])
            ->where('created_at', '>=', now()->subHours(48))
            ->whereNotIn('appointment_id', $acceptedIds)
            ->when($excludeAppointmentId, fn ($query) => $query->where('appointment_id', '!=', $excludeAppointmentId))
            ->orderByDesc('created_at')
            ->get()
            ->unique('appointment_id')
            ->map(function (object $offer) use ($appointments): ?Appointment {
                $body = json_decode((string) $offer->body, true);
                $source = $appointments->firstWhere('id', $offer->appointment_id);
                if (! $source || empty($body['proposed_start'])) return null;

                $start = Carbon::parse($body['proposed_start']);
                $duration = $source->starts_at->diffInMinutes($source->ends_at ?? $source->starts_at->copy()->addMinutes(60));
                $reservation = new Appointment([
                    'clinic_id' => $source->clinic_id,
                    'stylist_id' => (int) ($body['proposed_stylist_id'] ?? $source->stylist_id),
                    'starts_at' => $start->copy()->timezone(config('app.timezone')),
                    'ends_at' => $start->copy()->addMinutes($duration)->timezone(config('app.timezone')),
                    'status' => 'pending',
                    'reason' => 'Propuesta pendiente',
                ]);
                $reservation->id = -1 * (int) $offer->id;

                return $reservation;
            })
            ->filter()
            ->values();
    }
}
