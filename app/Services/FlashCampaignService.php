<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FlashCampaign;
use App\Models\FlashCampaignRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlashCampaignService
{
    public function __construct(private readonly TwilioSmsService $sms) {}

    public function eligibleClients(FlashCampaign $campaign): Builder
    {
        $channels = $campaign->channels ?? [];
        $query = Client::query()->where('clinic_id', $campaign->clinic_id);

        $query->where(function (Builder $eligible) use ($channels): void {
            if (in_array('email', $channels, true)) {
                $eligible->orWhere(fn (Builder $email) => $email
                    ->whereNotNull('email')->whereNotNull('marketing_email_consent_at'));
            }
            if (in_array('sms', $channels, true)) {
                $eligible->orWhere(fn (Builder $sms) => $sms
                    ->whereNotNull('phone')->whereNotNull('marketing_sms_consent_at'));
            }
        });

        return match ($campaign->segment) {
            'inactive' => $query->whereDoesntHave('appointments', fn (Builder $appointments) => $appointments
                ->whereIn('status', ['attended', 'completed', 'confirmed'])
                ->where('starts_at', '>=', now()->subDays(90))),
            'frequent' => $query->withCount(['appointments as qualifying_appointments_count' => fn (Builder $appointments) => $appointments
                ->whereIn('status', ['attended', 'completed'])
                ->where('starts_at', '>=', now()->subYear())])
                ->having('qualifying_appointments_count', '>=', 3),
            'vip' => $query->where('loyalty_level', 2),
            default => $query,
        };
    }

    public function prepareRecipients(FlashCampaign $campaign): int
    {
        $campaign->recipients()->delete();
        $channels = $campaign->channels ?? [];
        $count = 0;

        $this->eligibleClients($campaign)->orderBy('id')->chunkById(100, function ($clients) use ($campaign, $channels, &$count): void {
            foreach ($clients as $client) {
                $email = in_array('email', $channels, true) && $client->marketing_email_consent_at ? $client->email : null;
                $phone = in_array('sms', $channels, true) && $client->marketing_sms_consent_at ? $client->phone : null;
                if (! $email && ! $phone) {
                    continue;
                }
                $campaign->recipients()->create([
                    'client_id' => $client->id,
                    'token' => (string) Str::uuid(),
                    'email' => $email,
                    'phone' => $phone,
                    'email_status' => $email ? 'pending' : 'not_applicable',
                    'sms_status' => $phone ? 'pending' : 'not_applicable',
                ]);
                $count++;
            }
        });

        return $count;
    }

    public function send(FlashCampaign $campaign): void
    {
        abort_unless($campaign->status === 'draft', 409);
        abort_if($campaign->expires_at->isPast(), 422, 'La oferta ya vencio.');

        $campaign->update(['status' => 'sending']);
        $campaign->loadMissing(['clinic', 'service']);

        $campaign->recipients()->with('client')->chunkById(50, function ($recipients) use ($campaign): void {
            foreach ($recipients as $recipient) {
                $this->sendToRecipient($campaign, $recipient);
            }
        });

        $campaign->update(['status' => 'active', 'sent_at' => now()]);
    }

    public function bookingUrl(FlashCampaignRecipient $recipient): string
    {
        $campaign = $recipient->campaign;

        return route('public-bookings.create', $campaign->clinic_id).'?'.http_build_query([
            'service_id' => $campaign->service_id,
            'offer' => $recipient->token,
        ]);
    }

    public function smsBody(FlashCampaign $campaign, FlashCampaignRecipient $recipient): string
    {
        $clinic = $campaign->clinic?->name ?? 'Secretary365';
        $service = $campaign->service?->name ?? 'servicio seleccionado';
        $price = $campaign->discounted_price_cents !== null ? ' por '.number_format($campaign->discounted_price_cents / 100, 2).' EUR' : '';
        $expires = $campaign->expires_at->timezone($campaign->clinic?->localTimezone() ?: config('app.timezone'))->format('d/m H:i');

        return "{$clinic}: {$campaign->discount_percent}% de descuento en {$service}{$price}. Valida hasta {$expires}. Reserva: {$this->bookingUrl($recipient)}. STOP para dejar de recibir ofertas.";
    }

    private function sendToRecipient(FlashCampaign $campaign, FlashCampaignRecipient $recipient): void
    {
        $url = $this->bookingUrl($recipient);

        if ($recipient->email) {
            try {
                $subject = $campaign->subject ?: 'Oferta flash de '.$campaign->clinic->name;
                $text = $this->emailText($campaign, $recipient, $url);
                $html = $this->emailHtml($campaign, $recipient, $url);
                Mail::send([], [], function ($mail) use ($recipient, $subject, $text, $html): void {
                    $mail->to($recipient->email)->subject($subject);
                    $mail->getSymfonyMessage()->html($html)->text($text);
                });
                $recipient->update(['email_status' => 'sent', 'email_sent_at' => now()]);
            } catch (\Throwable $exception) {
                $recipient->update(['email_status' => 'failed', 'email_error' => $exception->getMessage()]);
                Log::warning('No se pudo enviar una oferta flash por correo.', ['recipient_id' => $recipient->id, 'error' => $exception->getMessage()]);
            }
        }

        if ($recipient->phone) {
            try {
                $providerId = $this->sms->send($recipient->phone, $this->smsBody($campaign, $recipient), route('campaigns.sms-status'));
                $recipient->update([
                    'sms_status' => $providerId ? 'sent' : 'unavailable',
                    'sms_provider_id' => $providerId,
                    'sms_sent_at' => $providerId ? now() : null,
                    'sms_error' => $providerId ? null : 'El proveedor SMS no esta configurado.',
                ]);
            } catch (\Throwable $exception) {
                $recipient->update(['sms_status' => 'failed', 'sms_error' => $exception->getMessage()]);
                Log::warning('No se pudo enviar una oferta flash por SMS.', ['recipient_id' => $recipient->id, 'error' => $exception->getMessage()]);
            }
        }
    }

    private function emailText(FlashCampaign $campaign, FlashCampaignRecipient $recipient, string $url): string
    {
        $name = $recipient->client?->first_name ?: 'cliente';
        $service = $campaign->service?->name ?? 'servicio seleccionado';
        $expires = $campaign->expires_at->timezone($campaign->clinic->localTimezone())->format('d/m/Y H:i');

        return "Hola {$name},\n\n{$campaign->message}\n\nOferta: {$campaign->discount_percent}% de descuento en {$service}.\nValida hasta: {$expires}.\nReserva aqui: {$url}\n\nRecibes este mensaje porque aceptaste ofertas por correo.";
    }

    private function emailHtml(FlashCampaign $campaign, FlashCampaignRecipient $recipient, string $url): string
    {
        $name = e($recipient->client?->first_name ?: 'cliente');
        $clinic = e($campaign->clinic->name);
        $service = e($campaign->service?->name ?? 'servicio seleccionado');
        $message = nl2br(e($campaign->message));
        $expires = e($campaign->expires_at->timezone($campaign->clinic->localTimezone())->format('d/m/Y H:i'));
        $price = $campaign->discounted_price_cents !== null ? number_format($campaign->discounted_price_cents / 100, 2).' EUR' : null;

        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#24151d"><div style="padding:28px;border:1px solid #eadfe5;border-radius:12px"><small style="color:#c0265a;font-weight:800">OFERTA FLASH · '.$clinic.'</small><h1 style="font-size:28px">'.$campaign->discount_percent.'% de descuento</h1><p>Hola '.$name.',</p><p>'.$message.'</p><div style="padding:16px;background:#fbf7f9;border-radius:8px"><b>'.$service.'</b>'.($price ? '<br><span style="font-size:22px">'.$price.'</span>' : '').'<br><small>Valida hasta '.$expires.'</small></div><p><a href="'.e($url).'" style="display:inline-block;padding:12px 18px;background:#c0265a;color:#fff;text-decoration:none;border-radius:6px;font-weight:800">Reservar oferta</a></p><small style="color:#70646b">Recibes este mensaje porque aceptaste ofertas por correo.</small></div></div>';
    }
}
