<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'monthly_price_cents',
        'stripe_price_id',
        'monthly_appointments_limit',
        'monthly_voice_minutes_limit',
        'monthly_sms_limit',
        'users_limit',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sidebarDescription(): string
    {
        $voice = $this->monthly_voice_minutes_limit === null
            ? 'llamadas ilimitadas'
            : $this->monthly_voice_minutes_limit.' minutos de voz';
        $sms = $this->monthly_sms_limit === null
            ? 'SMS ilimitados'
            : $this->monthly_sms_limit.' SMS';

        $features = collect($this->features ?? []);
        $extras = $features->contains('google_calendar')
            ? 'Google Calendar activo'
            : 'agenda de servicios activa';

        return "{$voice}, {$sms} y {$extras}.";
    }

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }
}
