<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarMapping extends Model
{
    protected $fillable = [
        'clinic_id',
        'stylist_id',
        'google_calendar_id',
        'google_calendar_name',
        'access_role',
        'is_primary',
        'is_enabled',
        'is_available',
        'last_detected_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_enabled' => 'boolean',
        'is_available' => 'boolean',
        'last_detected_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function stylist(): BelongsTo
    {
        return $this->belongsTo(Stylist::class);
    }
}
