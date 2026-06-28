<?php

namespace App\Http\Controllers;

use App\Models\FlashCampaign;
use App\Models\FlashCampaignRecipient;
use App\Models\Service;
use App\Services\ClinicResolver;
use App\Services\FlashCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FlashCampaignController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $campaigns = FlashCampaign::query()
            ->where('clinic_id', $clinic->id)
            ->with(['service', 'recipients.appointments.payments'])
            ->latest()->get();

        $campaigns->each(fn (FlashCampaign $campaign) => $this->attachMetrics($campaign));

        return view('campaigns.index', compact('clinic', 'campaigns'));
    }

    public function create(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());

        return view('campaigns.create', [
            'clinic' => $clinic,
            'services' => $clinic->services()->where('is_active', true)->whereNotNull('price_cents')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ClinicResolver $clinics, FlashCampaignService $campaigns): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->whereNotNull('price_cents'))],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:90'],
            'expires_at' => ['required', 'date', 'after:now'],
            'segment' => ['required', Rule::in(['all', 'inactive', 'frequent', 'vip'])],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:800'],
        ], ['channels.required' => 'Selecciona correo, SMS o ambos.']);

        $service = Service::findOrFail($data['service_id']);
        $data['expires_at'] = Carbon::parse($data['expires_at'], $clinic->localTimezone())->timezone(config('app.timezone'));
        $original = $service->price_cents;
        $discounted = $original !== null ? (int) round($original * (100 - (int) $data['discount_percent']) / 100) : null;
        $campaign = $clinic->flashCampaigns()->create(array_merge($data, [
            'created_by' => $request->user()->id,
            'original_price_cents' => $original,
            'discounted_price_cents' => $discounted,
            'status' => 'draft',
        ]));
        $count = $campaigns->prepareRecipients($campaign);

        return redirect()->route('campaigns.show', $campaign)->with('campaign_status', "Vista previa creada para {$count} cliente(s). Revisa todo antes de enviar.");
    }

    public function show(Request $request, FlashCampaign $campaign, ClinicResolver $clinics, FlashCampaignService $service): View
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($campaign, $clinic->id);
        $campaign->load(['service', 'recipients.client', 'recipients.appointments.payments']);
        $this->attachMetrics($campaign);
        $previewRecipient = $campaign->recipients->first();

        return view('campaigns.show', compact('clinic', 'campaign', 'previewRecipient', 'service'));
    }

    public function send(Request $request, FlashCampaign $campaign, ClinicResolver $clinics, FlashCampaignService $service): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($campaign, $clinic->id);
        abort_if($campaign->recipients()->count() === 0, 422, 'No hay destinatarios con consentimiento para los canales elegidos.');
        $service->send($campaign);

        return redirect()->route('campaigns.show', $campaign)->with('campaign_status', 'Oferta flash enviada correctamente.');
    }

    public function end(Request $request, FlashCampaign $campaign, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $this->ensureClinic($campaign, $clinic->id);
        abort_unless(in_array($campaign->status, ['active', 'sending'], true), 409);
        $campaign->update(['status' => 'ended', 'ended_at' => now()]);

        return back()->with('campaign_status', 'La oferta fue finalizada. Sus enlaces ya no aplicaran el descuento.');
    }

    public function smsStatus(Request $request): \Illuminate\Http\Response
    {
        $sid = (string) $request->input('MessageSid');
        $status = (string) $request->input('MessageStatus');
        if ($sid && $status) {
            FlashCampaignRecipient::query()->where('sms_provider_id', $sid)->update(['sms_status' => $status]);
        }

        return response('', 204);
    }

    private function attachMetrics(FlashCampaign $campaign): void
    {
        $appointments = $campaign->recipients->flatMap->appointments
            ->whereNotIn('status', ['cancelled', 'canceled']);
        $revenue = $appointments->sum(fn ($appointment) => $appointment->payments->where('status', 'paid')->sum('amount_cents'));
        if ($revenue === 0) {
            $revenue = $appointments->sum(fn ($appointment) => (int) ($appointment->campaign_price_cents ?? 0));
        }
        $discount = $appointments->sum(fn ($appointment) => max(0, (int) $campaign->original_price_cents - (int) ($appointment->campaign_price_cents ?? 0)));
        $campaign->setAttribute('metrics', [
            'recipients' => $campaign->recipients->count(),
            'email_sent' => $campaign->recipients->where('email_status', 'sent')->count(),
            'sms_sent' => $campaign->recipients->whereIn('sms_status', ['sent', 'delivered'])->count(),
            'sms_delivered' => $campaign->recipients->where('sms_status', 'delivered')->count(),
            'bookings' => $appointments->count(),
            'revenue_cents' => $revenue,
            'discount_cents' => $discount,
        ]);
    }

    private function ensureClinic(FlashCampaign $campaign, int $clinicId): void
    {
        abort_unless($campaign->clinic_id === $clinicId, 404);
    }
}
