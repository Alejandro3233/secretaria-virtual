<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Stylist;
use App\Services\AppointmentReminderCallService;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicRescheduleController extends Controller
{
    public function confirm(Appointment $appointment, string $token, AppointmentReminderCallService $reminders, AppointmentService $appointments): View
    {
        $this->authorizeToken($appointment, $token, $reminders);
        $appointment->load(['clinic', 'client', 'service', 'stylist']);
        $this->recordClientResponse($appointment, 'confirm');

        if ($appointment->status === 'pending') {
            $appointments->confirm($appointment);
            $appointment->refresh()->load(['clinic', 'client', 'service', 'stylist']);
            $message = 'Tu cita fue confirmada correctamente.';
        } elseif ($appointment->status === 'confirmed') {
            $message = 'Tu cita ya estaba confirmada.';
        } elseif (in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            $appointments->confirm($appointment);
            $appointment->refresh()->load(['clinic', 'client', 'service', 'stylist']);
            $message = 'Tu cita fue reagendada y confirmada correctamente.';
        } else {
            $message = 'Recibimos tu respuesta.';
        }

        return view('public.reschedule.status', [
            'appointment' => $appointment,
            'title' => 'Cita confirmada',
            'message' => $message,
            'tone' => 'ok',
        ]);
    }

    public function cancel(Appointment $appointment, string $token, AppointmentReminderCallService $reminders, AppointmentService $appointments): View
    {
        $this->authorizeToken($appointment, $token, $reminders);
        $appointment->load(['clinic', 'client', 'service', 'stylist']);
        $this->recordClientResponse($appointment, 'cancel');

        if (! in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            $appointments->cancel($appointment);
            $appointment->refresh()->load(['clinic', 'client', 'service', 'stylist']);
            $message = 'Tu cita fue cancelada correctamente.';
        } elseif (in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            $message = 'Esta cita ya estaba cancelada.';
        }

        return view('public.reschedule.status', [
            'appointment' => $appointment,
            'title' => 'Cita cancelada',
            'message' => $message,
            'tone' => 'danger',
        ]);
    }

    public function show(Request $request, Appointment $appointment, string $token, AppointmentReminderCallService $reminders): View
    {
        $this->authorizeToken($appointment, $token, $reminders);
        $appointment->load(['clinic', 'client', 'service', 'stylist']);

        $timezone = $appointment->clinic->localTimezone();
        $selectedDate = $request->query('date')
            ? Carbon::parse((string) $request->query('date'), $timezone)
            : now($timezone);

        return view('public.reschedule.show', [
            'appointment' => $appointment,
            'token' => $token,
            'selectedDate' => $selectedDate,
            'dates' => collect(range(0, 13))->map(fn (int $day) => now($timezone)->addDays($day)),
            'availableSlots' => $this->availableSlots($appointment, $selectedDate),
        ]);
    }

    public function update(Request $request, Appointment $appointment, string $token, AppointmentReminderCallService $reminders, AppointmentService $appointments): RedirectResponse
    {
        $this->authorizeToken($appointment, $token, $reminders);
        $appointment->load(['clinic', 'service']);

        $data = $request->validate([
            'slot' => ['required', 'string'],
        ]);

        [$startsAtValue, $stylistId] = array_pad(explode('|', $data['slot'], 2), 2, null);
        $startsAt = Carbon::parse($startsAtValue, $appointment->clinic->localTimezone());
        $stylist = $stylistId ? Stylist::query()->where('clinic_id', $appointment->clinic_id)->where('is_active', true)->where('is_internal', false)->whereKey((int) $stylistId)->first() : null;
        if ($stylist && ! $stylist->canPerformService($appointment->service_id)) {
            $stylist = null;
        }
        $hasActiveStylists = Stylist::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->exists();

        if ($hasActiveStylists) {
            abort_unless($stylist && $this->stylistAvailable($appointment, $stylist, $startsAt), 409);
        } else {
            abort_unless($this->clinicSlotAvailable($appointment, $startsAt), 409);
        }

        $duration = $appointment->service?->duration_minutes
            ?? ($appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : 60);

        $appointment = $appointments->update($appointment, [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($duration),
            'stylist_id' => $stylist?->id,
            'status' => 'confirmed',
        ]);
        $this->recordClientResponse($appointment, 'reschedule');
        $token = $reminders->tokenFor($appointment);

        return redirect()->route('public-reschedule.show', [$appointment, $token])
            ->with('reschedule_status', 'Tu cita fue reagendada correctamente.');
    }

    private function availableSlots(Appointment $appointment, Carbon $date)
    {
        $appointment->loadMissing(['clinic', 'service']);
        $timezone = $appointment->clinic->localTimezone();
        $stylists = Stylist::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->when($appointment->service_id, fn ($query) => $query->where(function ($services) use ($appointment): void {
                $services->where('service_id', $appointment->service_id)
                    ->orWhereHas('services', fn ($assigned) => $assigned->whereKey($appointment->service_id));
            }))
            ->orderBy('name')
            ->get()
            ->filter(fn (Stylist $stylist) => $this->stylistWorksOnDate($stylist, $date));

        if ($stylists->isEmpty()) {
            $hasConfiguredStaff = Stylist::query()
                ->where('clinic_id', $appointment->clinic_id)
                ->where('is_active', true)
                ->where('is_internal', false)
                ->exists();

            if ($hasConfiguredStaff) {
                return collect();
            }

            $start = $date->copy()->timezone($timezone)->setTime(8, 0);
            $end = $date->copy()->timezone($timezone)->setTime(21, 0);
            $steps = max(0, (int) floor($start->diffInMinutes($end) / 30));

            return collect(range(0, $steps))
                ->map(fn (int $step) => $start->copy()->addMinutes($step * 30))
                ->filter(fn (Carbon $slot) => $slot->greaterThanOrEqualTo(now($timezone)) && $slot->lessThan($end))
                ->filter(fn (Carbon $slot) => $this->clinicSlotAvailable($appointment, $slot))
                ->map(fn (Carbon $slot): array => [
                    'starts_at' => $slot,
                    'stylist' => null,
                    'value' => $slot->format('Y-m-d H:i:s').'|',
                ])
                ->values();
        }

        $start = $stylists->map(fn (Stylist $stylist) => $this->workStartFor($stylist, $date, $timezone))->min();
        $end = $stylists->map(fn (Stylist $stylist) => $this->workEndFor($stylist, $date, $timezone))->max();
        $steps = max(0, (int) floor($start->diffInMinutes($end) / 30));

        return collect(range(0, $steps))
            ->map(fn (int $step) => $start->copy()->addMinutes($step * 30))
            ->filter(fn (Carbon $slot) => $slot->greaterThanOrEqualTo(now($timezone)) && $slot->lessThan($end))
            ->map(function (Carbon $slot) use ($appointment, $stylists): ?array {
                $stylist = $stylists->first(fn (Stylist $candidate) => $this->stylistAvailable($appointment, $candidate, $slot));

                if (! $stylist) {
                    return null;
                }

                return [
                    'starts_at' => $slot,
                    'stylist' => $stylist,
                    'value' => $slot->format('Y-m-d H:i:s').'|'.$stylist->id,
                ];
            })
            ->filter()
            ->values();
    }

    private function recordClientResponse(Appointment $appointment, string $response): void
    {
        $alreadyRecorded = DB::table('notifications')
            ->where('appointment_id', $appointment->id)
            ->where('event', 'appointment_client_response')
            ->where('body', $response)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'web',
            'event' => 'appointment_client_response',
            'recipient' => $appointment->client?->email ?: $appointment->client?->phone ?: 'unknown',
            'status' => 'received',
            'body' => $response,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stylistAvailable(Appointment $appointment, Stylist $stylist, Carbon $startsAt): bool
    {
        if (! $this->stylistWorksOnDate($stylist, $startsAt)) {
            return false;
        }

        if ($this->stylistOnVacation($stylist, $startsAt)) {
            return false;
        }

        $duration = $appointment->service?->duration_minutes
            ?? ($appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : 60);
        $endsAt = $startsAt->copy()->addMinutes($duration);
        $timezone = $appointment->clinic->localTimezone();

        if ($startsAt->lessThan($this->workStartFor($stylist, $startsAt, $timezone)) || $endsAt->greaterThan($this->workEndFor($stylist, $startsAt, $timezone))) {
            return false;
        }

        if ($stylist->isOnBreak($startsAt, $endsAt)) {
            return false;
        }

        return Appointment::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->where('id', '!=', $appointment->id)
            ->where('stylist_id', $stylist->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfDay()->timezone(config('app.timezone')),
                $startsAt->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->get()
            ->every(function (Appointment $existing) use ($startsAt, $endsAt): bool {
                $existingStart = $existing->starts_at;
                $existingEnd = $existing->ends_at ?? $existingStart->copy()->addMinutes($existing->service?->duration_minutes ?? 60);

                return $existingStart->greaterThanOrEqualTo($endsAt) || $existingEnd->lessThanOrEqualTo($startsAt);
            });
    }

    private function clinicSlotAvailable(Appointment $appointment, Carbon $startsAt): bool
    {
        $duration = $appointment->service?->duration_minutes
            ?? ($appointment->ends_at ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : 60);
        $endsAt = $startsAt->copy()->addMinutes($duration);
        $timezone = $appointment->clinic->localTimezone();
        $workStart = $startsAt->copy()->timezone($timezone)->setTime(8, 0);
        $workEnd = $startsAt->copy()->timezone($timezone)->setTime(21, 0);

        if ($startsAt->lessThan($workStart) || $endsAt->greaterThan($workEnd)) {
            return false;
        }

        return Appointment::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfDay()->timezone(config('app.timezone')),
                $startsAt->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->get()
            ->every(function (Appointment $existing) use ($startsAt, $endsAt): bool {
                $existingStart = $existing->starts_at;
                $existingEnd = $existing->ends_at ?? $existingStart->copy()->addMinutes($existing->service?->duration_minutes ?? 60);

                return $existingStart->greaterThanOrEqualTo($endsAt) || $existingEnd->lessThanOrEqualTo($startsAt);
            });
    }

    private function authorizeToken(Appointment $appointment, string $token, AppointmentReminderCallService $reminders): void
    {
        abort_unless($reminders->validToken($appointment, $token), 403);
    }

    private function stylistWorksOnDate(Stylist $stylist, Carbon $date): bool
    {
        return $stylist->worksOnDate($date);
    }

    private function stylistOnVacation(Stylist $stylist, Carbon $date): bool
    {
        return $stylist->vacations()
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->exists();
    }

    private function workStartFor(Stylist $stylist, Carbon $date, string $timezone): Carbon
    {
        [$hour, $minute] = $this->timeParts($stylist->scheduleForDate($date)['start'] ?? '08:00');

        return $date->copy()->timezone($timezone)->setTime($hour, $minute);
    }

    private function workEndFor(Stylist $stylist, Carbon $date, string $timezone): Carbon
    {
        [$hour, $minute] = $this->timeParts($stylist->scheduleForDate($date)['end'] ?? '21:00');

        return $date->copy()->timezone($timezone)->setTime($hour, $minute);
    }

    private function timeParts(string $time): array
    {
        $parts = explode(':', $time);

        return [(int) ($parts[0] ?? 8), (int) ($parts[1] ?? 0)];
    }
}
