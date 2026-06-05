<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TwilioSmsService
{
    public function send(string $to, string $body): ?string
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.from');
        $messagingServiceSid = config('services.twilio.messaging_service_sid');
        $to = $this->normalizePhone($to);

        if (! $accountSid || ! $authToken || ! $to || (! $from && ! $messagingServiceSid)) {
            return null;
        }

        $payload = [
            'To' => $to,
            'Body' => $body,
        ];

        if ($messagingServiceSid) {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = $from;
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload)
            ->throw();

        return $response->json('sid');
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return preg_replace('/[^\d+]/', '', $phone);
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        return null;
    }
}
