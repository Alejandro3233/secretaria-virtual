<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\AppointmentService;
use App\Services\ClinicResolver;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics, GoogleCalendarService $calendar): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $selectedDate = $request->date('date') ?? now();
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
                ->whereBetween('starts_at', [$queryStart, $queryEnd])
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->orderBy('starts_at');

            if ($selectedStylistIds->isNotEmpty()) {
                $appointmentsQuery->whereIn('stylist_id', $selectedStylistIds);
            }

            $appointments = $appointmentsQuery->get();
        }

        $days = collect(range(0, 6))->map(fn (int $day) => $weekStart->copy()->addDays($day));
        $monthStart = $selectedDate->copy()->startOfMonth()->startOfWeek();
        $miniCalendarDays = collect(range(0, 41))->map(fn (int $day) => $monthStart->copy()->addDays($day));
        $monthDays = collect(range(0, 41))->map(fn (int $day) => $monthStart->copy()->addDays($day));
        $visibleStylists = $selectedStylistIds->isEmpty()
            ? $stylists
            : $stylists->whereIn('id', $selectedStylistIds->all())->values();
        $hours = $appointments->isEmpty()
            ? range(9, 17)
            : range(
                max(0, min(9, $appointments->min(fn (Appointment $appointment) => $appointment->starts_at->hour))),
                min(23, max(17, $appointments->max(fn (Appointment $appointment) => ($appointment->ends_at ?? $appointment->starts_at)->hour)))
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
        ]);
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
            'status' => ['required', 'string', 'in:pending,confirmed'],
            'reason' => ['nullable', 'string', 'max:255'],
            'chair_station' => ['nullable', 'string', 'max:255'],
            'client_comments' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $client = $this->resolveClient($clinic, $data);
        $this->abortIfForeignServiceOrStylist($clinic->id, $data);

        $startsAt = Carbon::parse($data['starts_at']);
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
            'status' => $data['status'],
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
}
