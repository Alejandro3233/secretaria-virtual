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

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }
}
