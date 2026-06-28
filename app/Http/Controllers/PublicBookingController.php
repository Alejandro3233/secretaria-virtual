<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\FlashCampaignRecipient;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\AppointmentService;
use App\Services\GoogleCalendarService;
use App\Services\ResourceAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $googleCalendar, private readonly ResourceAvailabilityService $resources)
    {
    }

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        $clinics = Clinic::query()
            ->whereIn('subscription_status', ['trial', 'active'])
            ->when($query !== '', function ($clinicsQuery) use ($query): void {
                $clinicsQuery->where(function ($searchQuery) use ($query): void {
                    $searchQuery
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('address', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        return view('public.bookings.index', [
            'query' => $query,
            'clinics' => $clinics,
        ]);
    }

    public function show(Clinic $clinic): View
    {
        $this->abortIfUnavailable($clinic);

        return view('public.bookings.show', [
            'clinic' => $clinic,
            'services' => $clinic->services()->with('activeAddons')->where('is_active', true)->orderBy('name')->get(),
            'stylists' => $clinic->stylists()->where('is_active', true)->where('is_internal', false)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, Clinic $clinic): View
    {
        $this->abortIfUnavailable($clinic);
        $googleCalendarError = $this->syncGoogleCalendarIfConnected($clinic);
        $clinic->refresh();

        $services = $clinic->services()->with('activeAddons')->where('is_active', true)->orderBy('name')->get();
        $stylists = $clinic->stylists()->where('is_active', true)->where('is_internal', false)->orderBy('name')->get();
        $selectedService = $this->selectedService($clinic, $request, $services->first());
        $offerRecipient = $this->offerRecipient($clinic, (string) $request->query('offer'));
        if ($offerRecipient?->campaign?->service) {
            $selectedService = $offerRecipient->campaign->service;
            $offerRecipient->update(['opened_at' => $offerRecipient->opened_at ?: now()]);
        }
        if ($selectedService) {
            $stylists = $stylists->filter(fn (Stylist $stylist) => $stylist->canPerformService($selectedService->id))->values();
        }
        $selectedStylist = $this->selectedStylist($clinic, $request);
        if ($selectedStylist && ! $selectedStylist->canPerformService($selectedService?->id)) {
            $selectedStylist = null;
        }
        $timezone = $clinic->localTimezone();
        $selectedDate = $request->query('date')
            ? Carbon::parse((string) $request->query('date'), $timezone)
            : now($timezone);
        $offerExpiresAt = $offerRecipient?->campaign?->expires_at?->copy()->timezone($timezone);
        $lastDateOffset = (int) ($offerExpiresAt
            ? max(0, min(13, now($timezone)->startOfDay()->diffInDays($offerExpiresAt->copy()->startOfDay(), false)))
            : 13);

        return view('public.bookings.create', [
            'clinic' => $clinic,
            'services' => $services,
            'stylists' => $stylists,
            'selectedService' => $selectedService,
            'selectedStylist' => $selectedStylist,
            'selectedDate' => $selectedDate,
            'availableSlots' => $this->availableSlots($clinic, $selectedDate, $selectedService, $selectedStylist, $offerExpiresAt),
            'googleCalendarError' => $googleCalendarError,
            'dates' => collect(range(0, $lastDateOffset))->map(fn (int $day) => now($timezone)->addDays($day)),
            'offerRecipient' => $offerRecipient,
        ]);
    }

    public function store(Request $request, Clinic $clinic, AppointmentService $appointments): RedirectResponse
    {
        $this->abortIfUnavailable($clinic);
        $this->syncGoogleCalendarIfConnected($clinic);
        $clinic->refresh();

        $data = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'stylist_id' => ['nullable', 'integer', 'exists:stylists,id'],
            'starts_at' => ['required', 'date'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'client_comments' => ['nullable', 'string', 'max:1000'],
            'offer_token' => ['nullable', 'uuid'],
            'marketing_email_consent' => ['nullable', 'boolean'],
            'marketing_sms_consent' => ['nullable', 'boolean'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'distinct'],
        ]);

        $offerRecipient = $this->offerRecipient($clinic, (string) ($data['offer_token'] ?? ''));
        if ($offerRecipient?->campaign?->service_id) {
            $data['service_id'] = $offerRecipient->campaign->service_id;
        }

        $service = ! empty($data['service_id'])
            ? Service::query()->where('clinic_id', $clinic->id)->where('is_active', true)->findOrFail($data['service_id'])
            : null;
        $selectedAddons = $service
            ? $service->activeAddons()->whereIn('id', $data['addon_ids'] ?? [])->get()
            : collect();
        abort_unless($selectedAddons->count() === count($data['addon_ids'] ?? []), 422, 'Uno de los extras elegidos no esta disponible.');
        $stylist = ! empty($data['stylist_id'])
            ? Stylist::query()->where('clinic_id', $clinic->id)->where('is_active', true)->where('is_internal', false)->findOrFail($data['stylist_id'])
            : null;

        $startsAt = Carbon::parse($data['starts_at'], $clinic->localTimezone());

        abort_if(
            $offerRecipient && $startsAt->greaterThan($offerRecipient->campaign->expires_at->copy()->timezone($clinic->localTimezone())),
            422,
            'La fecha elegida queda fuera de la vigencia de la oferta.'
        );

        $availableStylist = $this->availableStylistForSlot($clinic, $startsAt, $service, $stylist);

        abort_unless($availableStylist !== null, 409);

        $clientValues = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'notification_preference' => 'both',
        ];
        if ($request->boolean('marketing_email_consent')) {
            $clientValues['marketing_email_consent_at'] = now();
        }
        if ($request->boolean('marketing_sms_consent')) {
            $clientValues['marketing_sms_consent_at'] = now();
        }

        $client = Client::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'phone' => $data['phone'],
            ],
            $clientValues
        );

        $durationMinutes = $service?->duration_minutes ?? 60;
        $appointment = $appointments->create($clinic, [
            'client_id' => $client->id,
            'service_id' => $service?->id,
            'stylist_id' => $availableStylist->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
            'status' => 'pending',
            'priority' => 'normal',
            'source' => 'public_booking',
            'reason' => $service?->name ?? 'Reserva publica',
            'client_comments' => $data['client_comments'] ?? null,
            'flash_campaign_recipient_id' => $offerRecipient?->id,
            'campaign_discount_percent' => $offerRecipient?->campaign?->discount_percent,
            'campaign_price_cents' => $offerRecipient?->campaign?->discounted_price_cents,
            'selected_addons' => $selectedAddons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price_cents' => $addon->price_cents,
            ])->values()->all(),
            'addons_total_cents' => $selectedAddons->sum('price_cents'),
        ]);

        return redirect("/salones/{$clinic->id}/reservar")
            ->with('booking_status', 'Tu cita fue creada para '.$appointment->starts_at->format('d/m/Y g:i A').'. Revisa tu correo para confirmarla, modificarla o cancelarla.');
    }

    private function selectedService(Clinic $clinic, Request $request, ?Service $fallback): ?Service
    {
        $serviceId = (int) $request->query('service_id');

        if (! $serviceId) {
            return $fallback;
        }

        return Service::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->find($serviceId) ?: $fallback;
    }

    private function offerRecipient(Clinic $clinic, string $token): ?FlashCampaignRecipient
    {
        if ($token === '') {
            return null;
        }

        return FlashCampaignRecipient::query()
            ->where('token', $token)
            ->whereHas('campaign', fn ($campaign) => $campaign
                ->where('clinic_id', $clinic->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->where('expires_at', '>', now()))
            ->with(['campaign.service', 'client'])
            ->first();
    }

    private function selectedStylist(Clinic $clinic, Request $request): ?Stylist
    {
        $stylistId = (int) $request->query('stylist_id');

        if (! $stylistId) {
            return null;
        }

        return Stylist::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->find($stylistId);
    }

    private function availableSlots(Clinic $clinic, Carbon $date, ?Service $service, ?Stylist $stylist, ?Carbon $offerExpiresAt = null): \Illuminate\Support\Collection
    {
        $timezone = $clinic->localTimezone();
        $workingStylists = $this->eligibleStylists($clinic, $service, $stylist)
            ->filter(fn (Stylist $eligibleStylist) => $this->stylistWorksOnDate($eligibleStylist, $date));

        if ($workingStylists->isEmpty()) {
            return collect();
        }

        $start = $workingStylists
            ->map(fn (Stylist $workingStylist) => $this->workStartFor($workingStylist, $date, $timezone))
            ->min();
        $end = $workingStylists
            ->map(fn (Stylist $workingStylist) => $this->workEndFor($workingStylist, $date, $timezone))
            ->max();

        if (! $start || ! $end || $start->greaterThanOrEqualTo($end)) {
            return collect();
        }

        $steps = max(0, (int) floor($start->diffInMinutes($end) / 30));

        return collect(range(0, $steps))
            ->map(fn (int $step) => $start->copy()->addMinutes($step * 30))
            ->filter(fn (Carbon $slot) => $slot->lessThan($end) && $slot->greaterThanOrEqualTo(now($timezone)))
            ->when($offerExpiresAt, fn ($slots) => $slots->filter(fn (Carbon $slot) => $slot->lessThanOrEqualTo($offerExpiresAt)))
            ->filter(fn (Carbon $slot) => $this->availableStylistForSlot($clinic, $slot, $service, $stylist) !== null)
            ->values();
    }

    private function availableStylistForSlot(Clinic $clinic, Carbon $startsAt, ?Service $service, ?Stylist $stylist): ?Stylist
    {
        return $this->eligibleStylists($clinic, $service, $stylist)
            ->first(fn (Stylist $eligibleStylist) => $this->stylistIsAvailableForSlot($clinic, $eligibleStylist, $startsAt, $service));
    }

    private function stylistIsAvailableForSlot(Clinic $clinic, Stylist $stylist, Carbon $startsAt, ?Service $service): bool
    {
        if (! $this->stylistWorksOnDate($stylist, $startsAt)) {
            return false;
        }

        if ($this->stylistOnVacation($stylist, $startsAt)) {
            return false;
        }

        $timezone = $clinic->localTimezone();
        $durationMinutes = $service?->duration_minutes ?? 60;
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $workStart = $this->workStartFor($stylist, $startsAt, $timezone);
        $workEnd = $this->workEndFor($stylist, $startsAt, $timezone);

        if ($startsAt->lessThan($workStart) || $endsAt->greaterThan($workEnd)) {
            return false;
        }

        if ($stylist->isOnBreak($startsAt, $endsAt)) {
            return false;
        }

        if (! $this->resources->available($service, $startsAt, $endsAt)) {
            return false;
        }

        return Appointment::query()
            ->with('service')
            ->where('clinic_id', $clinic->id)
            ->where('stylist_id', $stylist->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfDay()->timezone(config('app.timezone')),
                $startsAt->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->get()
            ->every(function (Appointment $appointment) use ($startsAt, $endsAt): bool {
                $appointmentStart = $appointment->starts_at;
                $appointmentEnd = $appointment->ends_at
                    ?? $appointmentStart->copy()->addMinutes($appointment->service?->duration_minutes ?? 60);

                return $appointmentStart->greaterThanOrEqualTo($endsAt)
                    || $appointmentEnd->lessThanOrEqualTo($startsAt);
            });
    }

    private function eligibleStylists(Clinic $clinic, ?Service $service, ?Stylist $stylist): \Illuminate\Support\Collection
    {
        if ($stylist) {
            return $stylist->canPerformService($service?->id) ? collect([$stylist]) : collect();
        }

        return Stylist::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->when($service, fn ($query) => $query->where(function ($services) use ($service): void {
                $services->where('service_id', $service->id)
                    ->orWhereHas('services', fn ($assigned) => $assigned->whereKey($service->id));
            }))
            ->orderBy('name')
            ->get();
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
        [$hour, $minute] = $this->timeParts($stylist->scheduleForDate($date)['start'] ?? '09:00');

        return $date->copy()->timezone($timezone)->setTime($hour, $minute);
    }

    private function workEndFor(Stylist $stylist, Carbon $date, string $timezone): Carbon
    {
        [$hour, $minute] = $this->timeParts($stylist->scheduleForDate($date)['end'] ?? '17:00');

        return $date->copy()->timezone($timezone)->setTime($hour, $minute);
    }

    private function timeParts(string $time): array
    {
        $parts = explode(':', $time);

        return [
            (int) ($parts[0] ?? 9),
            (int) ($parts[1] ?? 0),
        ];
    }

    private function syncGoogleCalendarIfConnected(Clinic $clinic): ?string
    {
        if (! $clinic->google_connected_at || ! $clinic->google_refresh_token) {
            return null;
        }

        try {
            $this->googleCalendar->syncClinic($clinic);

            return null;
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }

    private function abortIfUnavailable(Clinic $clinic): void
    {
        abort_unless(in_array($clinic->subscription_status, ['trial', 'active'], true), 404);
    }
}
