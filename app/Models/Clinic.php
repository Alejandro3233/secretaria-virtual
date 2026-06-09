<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'name',
        'email',
        'phone',
        'country_code',
        'twilio_phone_number',
        'twilio_phone_sid',
        'twilio_number_status',
        'twilio_number_error',
        'twilio_number_assigned_at',
        'address',
        'timezone',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_renews_at',
        'google_calendar_id',
        'google_calendar_summary',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'google_sync_token',
        'google_connected_at',
        'google_last_synced_at',
        'google_tts_voice',
    ];

    protected $casts = [
        'subscription_renews_at' => 'datetime',
        'twilio_number_assigned_at' => 'datetime',
        'google_access_token' => 'encrypted:array',
        'google_refresh_token' => 'encrypted',
        'google_token_expires_at' => 'datetime',
        'google_connected_at' => 'datetime',
        'google_last_synced_at' => 'datetime',
    ];

    public static function timezoneForCountry(?string $countryCode): string
    {
        return match (strtoupper((string) $countryCode)) {
            'ES' => 'Europe/Madrid',
            'GB' => 'Europe/London',
            'MX' => 'America/Mexico_City',
            'CO' => 'America/Bogota',
            default => 'America/New_York',
        };
    }

    public function localTimezone(): string
    {
        $timezone = $this->timezone ?: static::timezoneForCountry($this->country_code);

        if ($timezone === 'America/New_York' && strtoupper((string) $this->country_code) !== 'US') {
            return static::timezoneForCountry($this->country_code);
        }

        return $timezone;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function stylists(): HasMany
    {
        return $this->hasMany(Stylist::class);
    }
}
