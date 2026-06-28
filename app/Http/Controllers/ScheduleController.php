<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\AppointmentService;
use App\Services\ClinicResolver;
use App\Services\StylistScheduleService;
use App\Services\ResourceAvailabilityService;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                ->with(['vacations' => fn ($query) => $query
                    ->whereDate('starts_on', '<=', $queryEnd->toDateString())
                    ->whereDate('ends_on', '>=', $queryStart->toDateString())
                    ->orderBy('starts_on')])
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->when(! $clinic->google_ever_synced_at, fn ($query) => $query->where('is_internal', false))
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

            $notificationStates = DB::table('notifications')
                ->whereIn('appointment_id', $appointments->pluck('id'))
                ->where(function ($query): void {
                    $query->where(function ($sent): void {
                        $sent->whereIn('channel', ['sms', 'email'])
                            ->whereIn('status', ['sent', 'queued']);
                    })->orWhere('event', 'appointment_client_response');
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
            'stylists' => $clinic->stylists()->with('services')->where('is_active', true)->where('is_internal', false)->orderBy('name')->get(),
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

    public function store(Request $request, AppointmentService $appointments, ClinicResolver $clinics, StylistScheduleService $schedules, ResourceAvailabilityService $resources): RedirectResponse
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

        $this->abortIfForeignServiceOrStylist($clinic->id, $data);

        $startsAt = Carbon::parse($data['starts_at'], $clinic->localTimezone());
        $service = ! empty($data['service_id'])
            ? Service::query()->where('clinic_id', $clinic->id)->find($data['service_id'])
            : null;
        $durationMinutes = $service?->duration_minutes ?? (int) $data['duration_minutes'];
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $stylist = ! empty($data['stylist_id'])
            ? Stylist::query()->where('clinic_id', $clinic->id)->findOrFail($data['stylist_id'])
            : null;

        if ($stylist && $service && ! $stylist->canPerformService($service->id)) {
            throw ValidationException::withMessages(['stylist_id' => 'Este empleado no tiene asociado el servicio seleccionado.']);
        }

        if ($stylist && ($scheduleError = $schedules->validationMessage($stylist, $startsAt, $endsAt))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => $scheduleError]);
        }

        if ($resourceError = $resources->validationMessage($service, $startsAt, $endsAt)) {
            throw ValidationException::withMessages(['starts_at' => $resourceError]);
        }

        $client = $this->resolveClient($clinic, $data);
        $createdClient = $client->wasRecentlyCreated;

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
        if ($createdClient) {
            $message .= ' Cliente nuevo guardado en Clientes.';
        }

        return redirect('/agenda')->with('google_calendar_status', $message);
    }

    public function move(Request $request, Appointment $appointment, AppointmentService $appointments, StylistScheduleService $schedules, ResourceAvailabilityService $resources): JsonResponse
    {
        $clinic = $this->appointmentClinic($request, $appointment);
        $appointment->loadMissing('service');

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'minutes' => ['required', 'integer', 'min:0', 'max:1439', 'multiple_of:5'],
            'stylist_id' => ['nullable', 'integer', 'exists:stylists,id'],
        ]);

        $stylist = ! empty($data['stylist_id'])
            ? Stylist::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->findOrFail($data['stylist_id'])
            : null;

        $timezone = $clinic->localTimezone();
        $startsAt = Carbon::parse($data['date'], $timezone)
            ->startOfDay()
            ->addMinutes((int) $data['minutes']);
        $durationMinutes = $appointment->ends_at
            ? max(15, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at))
            : (int) ($appointment->service?->duration_minutes ?? 60);
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        if ($stylist && $appointment->service_id && ! $stylist->canPerformService($appointment->service_id)) {
            return response()->json(['message' => 'Este empleado no realiza el servicio de la cita.'], 422);
        }

        if ($stylist && ($scheduleError = $schedules->validationMessage($stylist, $startsAt, $endsAt))) {
            return response()->json(['message' => $scheduleError], 422);
        }

        if ($resourceError = $resources->validationMessage($appointment->service, $startsAt, $endsAt, $appointment->id)) {
            return response()->json(['message' => $resourceError], 422);
        }

        $conflict = Appointment::query()
            ->with('service')
            ->where('clinic_id', $clinic->id)
            ->where('stylist_id', $stylist?->id)
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
                'stylist_id' => $stylist?->id,
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
                'starts_at_iso' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at?->format('g:i A'),
                'ends_at_iso' => $appointment->ends_at?->toIso8601String(),
                'stylist' => $appointment->stylist?->name,
                'traffic_class' => $appointment->trafficLightClass(),
                'traffic_label' => $appointment->trafficLightLabel(),
            ],
        ]);
    }

    private function resolveClient($clinic, array $data): Client
    {
        if (! empty($data['client_id'])) {
            $selectedClient = Client::query()
                ->where('clinic_id', $clinic->id)
                ->findOrFail($data['client_id']);

            if (! $this->newClientFieldsDifferFrom($selectedClient, $data)) {
                return $selectedClient;
            }

            if (blank($data['client_first_name'] ?? null) || blank($data['client_phone'] ?? null)) {
                throw ValidationException::withMessages([
                    'client_first_name' => 'Completa nombre y telefono para guardar un cliente nuevo.',
                    'client_phone' => 'Completa nombre y telefono para guardar un cliente nuevo.',
                ]);
            }
        }

        return Client::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'phone' => trim((string) $data['client_phone']),
            ],
            [
                'first_name' => trim((string) $data['client_first_name']),
                'last_name' => filled($data['client_last_name'] ?? null) ? trim((string) $data['client_last_name']) : null,
                'email' => filled($data['client_email'] ?? null) ? trim((string) $data['client_email']) : null,
                'notification_preference' => 'both',
            ]
        );
    }

    private function newClientFieldsDifferFrom(Client $client, array $data): bool
    {
        $submitted = [
            'first_name' => trim((string) ($data['client_first_name'] ?? '')),
            'last_name' => trim((string) ($data['client_last_name'] ?? '')),
            'phone' => trim((string) ($data['client_phone'] ?? '')),
            'email' => trim((string) ($data['client_email'] ?? '')),
        ];

        if (implode('', $submitted) === '') {
            return false;
        }

        $current = [
            'first_name' => trim((string) $client->first_name),
            'last_name' => trim((string) $client->last_name),
            'phone' => trim((string) $client->phone),
            'email' => trim((string) $client->email),
        ];

        return $submitted !== $current;
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
