<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioPhoneNumberService
{
    public const COUNTRIES = [
        'US' => 'Estados Unidos',
        'CA' => 'Canada',
        'GB' => 'Reino Unido',
        'ES' => 'Espana',
        'MX' => 'Mexico',
        'CO' => 'Colombia',
    ];

    private const TYPE_ENDPOINTS = [
        'local' => 'Local',
        'mobile' => 'Mobile',
        'toll_free' => 'TollFree',
    ];

    public function supportedCountries(): array
    {
        $configured = config('services.twilio.supported_countries') ?: array_keys(self::COUNTRIES);

        return collect($configured)
            ->map(fn (string $country): string => strtoupper($country))
            ->filter(fn (string $country): bool => array_key_exists($country, self::COUNTRIES))
            ->mapWithKeys(fn (string $country): array => [$country => self::COUNTRIES[$country]])
            ->all();
    }

    public function autoBuyEnabled(): bool
    {
        return filter_var(config('services.twilio.auto_buy_numbers'), FILTER_VALIDATE_BOOL);
    }

    public function assignToClinic(Clinic $clinic, bool $force = false): Clinic
    {
        if ($clinic->twilio_phone_number && ! $force) {
            return $clinic;
        }

        if (! in_array($clinic->subscription_status, ['active', 'trial'], true)) {
            return $this->markPending($clinic, 'El plan no esta activo.');
        }

        $country = strtoupper($clinic->country_code ?: 'US');

        if (! array_key_exists($country, $this->supportedCountries())) {
            return $this->markPending($clinic, 'Pais no disponible para asignacion automatica.');
        }

        try {
            $number = $this->firstAvailableNumber($country);

            if (! $number) {
                return $this->markPending($clinic, 'Twilio no encontro numeros disponibles para este pais.');
            }

            $purchased = $this->purchaseNumber($clinic, $number);

            $clinic->forceFill([
                'twilio_phone_number' => $purchased['phone_number'] ?? $number,
                'twilio_phone_sid' => $purchased['sid'] ?? null,
                'twilio_number_status' => 'active',
                'twilio_number_error' => null,
                'twilio_number_assigned_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            return $this->markPending($clinic, $exception->getMessage());
        }

        return $clinic->fresh();
    }

    private function firstAvailableNumber(string $country): ?string
    {
        foreach ($this->numberTypes() as $type) {
            $endpoint = self::TYPE_ENDPOINTS[$type] ?? null;

            if (! $endpoint) {
                continue;
            }

            $response = $this->request()
                ->get($this->twilioUrl("AvailablePhoneNumbers/{$country}/{$endpoint}.json"), [
                    'VoiceEnabled' => 'true',
                    'SmsEnabled' => 'true',
                    'PageSize' => 1,
                ]);

            if (! $response->successful()) {
                continue;
            }

            $numbers = $response->json('available_phone_numbers') ?? [];

            if (! empty($numbers[0]['phone_number'])) {
                return $numbers[0]['phone_number'];
            }
        }

        return null;
    }

    private function purchaseNumber(Clinic $clinic, string $phoneNumber): array
    {
        $response = $this->request()
            ->asForm()
            ->post($this->twilioUrl('IncomingPhoneNumbers.json'), [
                'PhoneNumber' => $phoneNumber,
                'FriendlyName' => 'Secretaria Virtual - '.$clinic->name,
                'VoiceUrl' => config('services.twilio.voice_webhook_url'),
                'VoiceMethod' => 'POST',
            ])
            ->throw();

        return $response->json();
    }

    private function markPending(Clinic $clinic, string $error): Clinic
    {
        $clinic->forceFill([
            'twilio_number_status' => 'pending',
            'twilio_number_error' => $error,
        ])->save();

        return $clinic->fresh();
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');

        if (! $accountSid || ! $authToken) {
            throw new RuntimeException('Configura TWILIO_ACCOUNT_SID y TWILIO_AUTH_TOKEN.');
        }

        return Http::withBasicAuth($accountSid, $authToken);
    }

    private function twilioUrl(string $path): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid').'/'.$path;
    }

    private function numberTypes(): array
    {
        return config('services.twilio.number_types') ?: ['local', 'mobile'];
    }
}
