<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Services\ClinicResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        return $this->page($request, $clinics);
    }

    public function show(Request $request, Client $client, ClinicResolver $clinics): View
    {
        return $this->page($request, $clinics, $client);
    }

    public function create(Request $request, ClinicResolver $clinics): View
    {
        return view('clients.form', [
            'clinic' => $clinics->currentOrCreate($request->user()),
            'client' => new Client(),
        ]);
    }

    public function store(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $this->validated($request, $clinic->id);
        $client = $clinic->clients()->create($data);

        return redirect()->route('clients.show', $client)->with('client_status', 'Cliente creado correctamente.');
    }

    public function edit(Request $request, Client $client, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($client, $clinic->id);

        return view('clients.form', compact('clinic', 'client'));
    }

    public function update(Request $request, Client $client, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($client, $clinic->id);
        $client->update($this->validated($request, $clinic->id, $client->id));

        return redirect()->route('clients.show', $client)->with('client_status', 'Datos del cliente actualizados.');
    }

    public function attendance(Request $request, Client $client, Appointment $appointment, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($client, $clinic->id);
        abort_unless($appointment->clinic_id === $clinic->id && $appointment->client_id === $client->id, 404);

        $data = $request->validate(['attendance' => ['required', Rule::in(['attended', 'no_show'])]]);
        $appointment->update(['status' => $data['attendance']]);

        return redirect()->route('clients.show', $client)->with(
            'client_status',
            $data['attendance'] === 'attended' ? 'Asistencia registrada.' : 'Inasistencia registrada.',
        );
    }

    public function loyalty(Request $request, Client $client, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($client, $clinic->id);
        $data = $request->validate([
            'loyalty_level' => ['required', 'integer', Rule::in([0, 1, 2])],
        ]);

        $client->update(['loyalty_level' => (int) $data['loyalty_level']]);

        $label = match ((int) $data['loyalty_level']) {
            1 => 'Cliente marcado como Favorito.',
            2 => 'Cliente marcado como VIP.',
            default => 'Clasificación especial eliminada.',
        };

        return redirect()->route('clients.show', $client)->with('client_status', $label);
    }

    private function page(Request $request, ClinicResolver $clinics, ?Client $selected = null): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        if ($selected) {
            $this->ensureClinic($selected, $clinic->id);
        }

        $search = trim((string) $request->query('q'));
        $loyaltyFilter = in_array($request->query('categoria'), ['favoritos', 'vip'], true)
            ? (string) $request->query('categoria')
            : 'todos';
        $clients = Client::query()
            ->where('clinic_id', $clinic->id)
            ->when($loyaltyFilter === 'favoritos', fn ($query) => $query->where('loyalty_level', 1))
            ->when($loyaltyFilter === 'vip', fn ($query) => $query->where('loyalty_level', 2))
            ->when($search !== '', fn ($query) => $query->where(function ($inside) use ($search): void {
                $inside->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('first_name')->orderBy('last_name')->get();

        $selected ??= $clients->first();
        if ($selected) {
            $selected->load(['preferences', 'appointments' => fn ($query) => $query
                ->with(['service', 'stylist'])->orderByDesc('starts_at')]);
        }

        $appointments = $selected?->appointments ?? collect();
        $clientCancellationResponses = $appointments->isEmpty()
            ? collect()
            : DB::table('notifications')
                ->whereIn('appointment_id', $appointments->pluck('id'))
                ->where('event', 'appointment_client_response')
                ->where('body', 'cancel')
                ->orderByDesc('created_at')
                ->get()
                ->unique('appointment_id')
                ->keyBy('appointment_id');

        $appointments->each(function (Appointment $appointment) use ($clientCancellationResponses): void {
            if (! in_array($appointment->status, ['cancelled', 'canceled'], true)) {
                return;
            }

            $response = $clientCancellationResponses->get($appointment->id);
            $cancelledAt = Carbon::parse($response?->created_at ?? $appointment->updated_at);
            $noticeMinutes = max(0, (int) $cancelledAt->diffInMinutes($appointment->starts_at, false));
            $appointment->setAttribute('cancellation_at', $cancelledAt);
            $appointment->setAttribute('cancellation_by_client', (bool) $response);
            $appointment->setAttribute('cancellation_notice_minutes', $noticeMinutes);
            $appointment->setAttribute('cancellation_notice_label', $this->noticeLabel($noticeMinutes));
        });

        $past = $appointments->filter(fn (Appointment $appointment) =>
            $appointment->starts_at->isPast() || in_array($appointment->status, ['cancelled', 'canceled'], true)
        );
        $attended = $past->whereIn('status', ['attended', 'completed']);
        $cancelled = $appointments->whereIn('status', ['cancelled', 'canceled']);
        $noShows = $past->where('status', 'no_show');
        $revenueCents = $attended->sum(fn (Appointment $appointment): int => (int) ($appointment->service?->price_cents ?? 0));
        $clientNoticeMinutes = $appointments
            ->filter(fn (Appointment $appointment): bool => (bool) $appointment->getAttribute('cancellation_by_client'))
            ->pluck('cancellation_notice_minutes');
        $shortestNotice = $clientNoticeMinutes->isNotEmpty() ? (int) $clientNoticeMinutes->min() : null;
        [$riskLabel, $riskClass] = match (true) {
            $shortestNotice === null => ['Sin datos', 'info'],
            $shortestNotice < 3 * 60 => ['Muy alto', 'danger'],
            $shortestNotice < 6 * 60 => ['Alto', 'danger'],
            $shortestNotice < 24 * 60 => ['Medio', 'wait'],
            $shortestNotice < 48 * 60 => ['Bajo', 'info'],
            default => ['No riesgo', 'ok'],
        };

        return view('clients.index', [
            'clinic' => $clinic,
            'clients' => $clients,
            'selectedClient' => $selected,
            'upcomingAppointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->starts_at->isFuture() && ! in_array($appointment->status, ['cancelled', 'canceled'], true))->sortBy('starts_at'),
            'pastAppointments' => $past,
            'stats' => [
                'appointments' => $appointments->count(),
                'attended' => $attended->count(),
                'no_shows' => $noShows->count(),
                'cancelled' => $cancelled->count(),
                'last_visit' => $attended->sortByDesc('starts_at')->first()?->starts_at,
                'revenue_cents' => $revenueCents,
                'cancellation_risk' => $riskLabel,
                'cancellation_risk_class' => $riskClass,
                'client_cancellations_measured' => $clientNoticeMinutes->count(),
            ],
            'search' => $search,
            'loyaltyFilter' => $loyaltyFilter,
        ]);
    }

    private function validated(Request $request, int $clinicId, ?int $clientId = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', Rule::unique('clients', 'phone')->where('clinic_id', $clinicId)->ignore($clientId)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notification_preference' => ['required', Rule::in(['both', 'sms', 'email', 'none'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function ensureClinic(Client $client, int $clinicId): void
    {
        abort_unless($client->clinic_id === $clinicId, 404);
    }

    private function noticeLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min antes';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        if ($hours < 24) {
            return $hours.' h'.($remainingMinutes ? ' '.$remainingMinutes.' min' : '').' antes';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $days.' día'.($days === 1 ? '' : 's').($remainingHours ? ' '.$remainingHours.' h' : '').' antes';
    }
}
