<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\AppointmentService;
use App\Services\ClinicResolver;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics, GoogleCalendarService $calendar): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $timezone = $clinic->localTimezone();
        $selectedDate = $request->query('date')
            ? Carbon::parse((string) $request->query('date'), $timezone)
            : now($timezone);
        $selectedView = in_array($request->query('view'), ['day', 'week', 'month'], true)
            ? $request->query('view')
            : 'week';
        $weekStart = $selectedDate->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $queryStart = match ($selectedView) {
            'day' => $selectedDate->copy()->startOfDay(),
            'month' => $selectedDate->copy()->startOfMonth()->startOfWeek(),
            default => $weekStart->copy(),
        };
        $queryEnd = match ($selectedView) {
            'day' => $selectedDate->copy()->endOfDay(),
            'month' => $selectedDate->copy()->endOfMonth()->endOfWeek(),
            default => $weekEnd->copy(),
        };
        $googleCalendarError = null;
        $selectedStylistIds = collect($request->input('stylists', []))
            ->map(fn ($stylistId) => (int) $stylistId)
            ->filter()
            ->values();

        $appointments = collect();
        $stylists = collect();

        if ($clinic) {
            if ($clinic->google_connected_at && $clinic->google_refresh_token && ! $clinic->google_last_synced_at) {
                try {
                    $calendar->syncClinic($clinic);
                    $clinic->refresh();
                } catch (\Throwable $exception) {
                    $googleCalendarError = $exception->getMessage();
                }
            }

            $stylists = Stylist::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $appointmentsQuery = Appointment::query()
                ->with(['client', 'service', 'stylist'])
                ->where('clinic_id', $clinic->id)
                ->whereBetween('starts_at', [
                    $queryStart->copy()->timezone(config('app.timezone')),
                    $queryEnd->copy()->timezone(config('app.timezone')),
                ])
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->orderBy('starts_at');

            if ($selectedStylistIds->isNotEmpty()) {
                $appointmentsQuery->whereIn('stylist_id', $selectedStylistIds);
            }

            $appointments = $appointmentsQuery->get()
                ->each(function (Appointment $appointment) use ($timezone): void {
                    $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
                    $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
                });
        }

        $days = collect(range(0, 6))->map(fn (int $day) => $weekStart->copy()->addDays($day));
        $monthStart = $selectedDate->copy()->startOfMonth()->startOfWeek();
        $miniCalendarDays = collect(range(0, 41))->map(fn (int $day) => $monthStart->copy()->addDays($day));
        $monthDays = collect(range(0, 41))->map(fn (int $day) => $monthStart->copy()->addDays($day));
        $visibleStylists = $selectedStylistIds->isEmpty()
            ? $stylists
            : $stylists->whereIn('id', $selectedStylistIds->all())->values();
        $hours = $appointments->isEmpty()
            ? range(8, 21)
            : range(
                max(0, min(8, $appointments->min(fn (Appointment $appointment) => $appointment->starts_at->hour))),
                min(23, max(21, $appointments->max(fn (Appointment $appointment) => ($appointment->ends_at ?? $appointment->starts_at)->hour)))
            );

        return view('schedule.index', [
            'clinic' => $clinic,
            'appointments' => $appointments,
            'appointmentsBySlot' => $appointments->groupBy(fn (Appointment $appointment) => $appointment->starts_at->format('Y-m-d-H')),
            'days' => $days,
            'hours' => $hours,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'selectedDate' => $selectedDate,
            'selectedView' => $selectedView,
            'miniCalendarDays' => $miniCalendarDays,
            'monthDays' => $monthDays,
            'stylists' => $stylists,
            'visibleStylists' => $visibleStylists,
            'selectedStylistIds' => $selectedStylistIds,
            'todayAppointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->starts_at->isToday()),
            'googleAppointments' => $appointments->where('source', 'google_calendar'),
            'googleCalendarError' => $googleCalendarError,
            'timezone' => $timezone,
        ]);
    }

    public function create(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());

        return view('schedule.create', [
            'clinic' => $clinic,
            'clients' => $clinic->clients()->orderBy('first_name')->get(),
            'services' => $clinic->services()->where('is_active', true)->orderBy('name')->get(),
            'stylists' => $clinic->stylists()->where('is_active', true)->orderBy('name')->get(),
            'timezone' => $clinic->localTimezone(),
        ]);
    }

    public function clients(Request $request, ClinicResolver $clinics): \Illuminate\Http\JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $query = trim((string) $request->query('q', ''));

        if (Str::length($query) < 2) {
            return response()->json([]);
        }

        $clients = Client::query()
            ->where('clinic_id', $clinic->id)
            ->where(function ($clientsQuery) use ($query) {
                $clientsQuery
                    ->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('first_name')
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email']);

        return response()->json($clients->map(fn (Client $client): array => [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'phone' => $client->phone,
            'email' => $client->email,
            'label' => trim($client->first_name.' '.$client->last_name),
        ]));
    }

    public function store(Request $request, AppointmentService $appointments, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());

        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_first_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'client_last_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['required_without:client_id', 'nullable', 'string', 'max:40'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'stylist_id' => ['nullable', 'integer', 'exists:stylists,id'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'chair_station' => ['nullable', 'string', 'max:255'],
            'client_comments' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $client = $this->resolveClient($clinic, $data);
        $this->abortIfForeignServiceOrStylist($clinic->id, $data);

        $startsAt = Carbon::parse($data['starts_at'], $clinic->localTimezone());
        $service = ! empty($data['service_id'])
            ? Service::query()->where('clinic_id', $clinic->id)->find($data['service_id'])
            : null;
        $durationMinutes = $service?->duration_minutes ?? (int) $data['duration_minutes'];
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        $appointment = $appointments->create($clinic, [
            'client_id' => $client->id,
            'service_id' => $data['service_id'] ?? null,
            'stylist_id' => $data['stylist_id'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
            'priority' => 'normal',
            'source' => 'web',
            'reason' => $data['reason'] ?? null,
            'chair_station' => $data['chair_station'] ?? null,
            'client_comments' => $data['client_comments'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);

        $message = $appointment->google_sync_status === 'synced'
            ? 'Cita creada y sincronizada con Google Calendar.'
            : 'Cita creada. Se sincronizara con Google Calendar cuando haya conexion activa.';

        return redirect('/agenda')->with('google_calendar_status', $message);
    }

    public function move(Request $request, Appointment $appointment, AppointmentService $appointments): JsonResponse
    {
        $clinic = $this->appointmentClinic($request, $appointment);
        $appointment->loadMissing('service');

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'stylist_id' => ['required', 'integer', 'exists:stylists,id'],
        ]);

        $stylist = Stylist::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->findOrFail($data['stylist_id']);

        $timezone = $clinic->localTimezone();
        $startsAt = Carbon::parse($data['date'], $timezone)
            ->startOfDay()
            ->addMinutes((int) $data['minutes']);
        $durationMinutes = $appointment->ends_at
            ? max(15, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at))
            : (int) ($appointment->service?->duration_minutes ?? 60);
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        $conflict = Appointment::query()
            ->with('service')
            ->where('clinic_id', $clinic->id)
            ->where('stylist_id', $stylist->id)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfDay()->timezone(config('app.timezone')),
                $startsAt->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->get()
            ->contains(function (Appointment $existing) use ($startsAt, $endsAt): bool {
                $existingStart = $existing->starts_at->copy()->timezone($startsAt->getTimezone());
                $existingEnd = ($existing->ends_at ?? $existing->starts_at->copy()->addMinutes($existing->service?->duration_minutes ?? 60))
                    ->copy()
                    ->timezone($startsAt->getTimezone());

                return $existingStart->lessThan($endsAt) && $existingEnd->greaterThan($startsAt);
            });

        if ($conflict) {
            return response()->json([
                'message' => 'Ese horario choca con otra cita del estilista.',
            ], 422);
        }

        try {
            $appointment = $appointments->update($appointment, [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'stylist_id' => $stylist->id,
            ])->load(['client', 'service', 'stylist']);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'No se pudo mover la cita. Revisa la sincronizacion e intentalo de nuevo.',
            ], 422);
        }

        $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
        $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);

        return response()->json([
            'message' => 'Cita movida correctamente.',
            'appointment' => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->format('g:i A'),
                'ends_at' => $appointment->ends_at?->format('g:i A'),
                'stylist' => $appointment->stylist?->name,
            ],
        ]);
    }

    private function resolveClient($clinic, array $data): Client
    {
        if (! empty($data['client_id'])) {
            return Client::query()
                ->where('clinic_id', $clinic->id)
                ->findOrFail($data['client_id']);
        }

        return Client::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'phone' => $data['client_phone'],
            ],
            [
                'first_name' => $data['client_first_name'],
                'last_name' => $data['client_last_name'] ?? null,
                'email' => $data['client_email'] ?? null,
                'notification_preference' => 'both',
            ]
        );
    }

    private function abortIfForeignServiceOrStylist(int $clinicId, array $data): void
    {
        if (! empty($data['service_id'])) {
            abort_unless(
                Service::query()->where('clinic_id', $clinicId)->whereKey($data['service_id'])->exists(),
                404
            );
        }

        if (! empty($data['stylist_id'])) {
            abort_unless(
                Stylist::query()->where('clinic_id', $clinicId)->whereKey($data['stylist_id'])->exists(),
                404
            );
        }
    }

    private function appointmentClinic(Request $request, Appointment $appointment)
    {
        $clinic = $request->user()->primaryClinic();

        abort_unless($clinic && $appointment->clinic_id === $clinic->id, 404);

        return $clinic;
    }
}
