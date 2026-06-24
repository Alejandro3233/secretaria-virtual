<?php

namespace App\Services;

use App\Models\User;

class CallForwardingOnboardingService
{
    public function destinationFor(User $user, string $fallback = '/consola'): string
    {
        $clinic = $user->primaryClinic();

        if (! $clinic?->twilio_phone_number) {
            return $fallback;
        }

        $preferences = $clinic->notification_preferences ?? [];

        if (! empty($preferences['call_forwarding_onboarding_seen_at'])) {
            return $fallback;
        }

        $preferences['call_forwarding_onboarding_seen_at'] = now()->toIso8601String();
        $clinic->forceFill(['notification_preferences' => $preferences])->save();

        return '/ajustes#servicios';
    }
}
