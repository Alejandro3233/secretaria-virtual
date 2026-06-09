<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'clinic_id',
        'client_id',
        'service_id',
        'stylist_id',
        'starts_at',
        'ends_at',
        'status',
        'priority',
        'source',
        'reason',
        'chair_station',
        'deposit_cents',
        'client_comments',
        'internal_notes',
        'google_calendar_id',
        'google_calendar_event_id',
        'google_synced_at',
        'google_sync_status',
        'google_sync_error',
        'reminder_call_enabled',
        'reminder_sms_enabled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'google_synced_at' => 'datetime',
        'reminder_call_enabled' => 'boolean',
        'reminder_sms_enabled' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function stylist(): BelongsTo
    {
        return $this->belongsTo(Stylist::class);
    }
}
