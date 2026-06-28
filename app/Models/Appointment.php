<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'flash_campaign_recipient_id',
        'campaign_discount_percent',
        'campaign_price_cents',
        'selected_addons',
        'addons_total_cents',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'google_synced_at' => 'datetime',
        'reminder_call_enabled' => 'boolean',
        'reminder_sms_enabled' => 'boolean',
        'campaign_discount_percent' => 'integer',
        'campaign_price_cents' => 'integer',
        'selected_addons' => 'array',
        'addons_total_cents' => 'integer',
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

    public function payments(): HasMany
    {
        return $this->hasMany(AppointmentPayment::class);
    }

    public function flashCampaignRecipient(): BelongsTo
    {
        return $this->belongsTo(FlashCampaignRecipient::class);
    }

    public function trafficLightClass(?CarbonInterface $reference = null): string
    {
        if (in_array($this->status, ['cancelled', 'canceled'], true)) {
            return 'appointment-cancelled';
        }

        if (in_array($this->status, ['confirmed', 'completed'], true)) {
            return 'appointment-confirmed';
        }

        $reference ??= now($this->starts_at?->getTimezone());
        $hoursUntilStart = $reference->diffInMinutes($this->starts_at, false) / 60;

        return match (true) {
            $hoursUntilStart > 24 => 'appointment-pending',
            $hoursUntilStart > 12 => 'appointment-urgent-light',
            $hoursUntilStart > 6 => 'appointment-urgent-medium',
            default => 'appointment-urgent-high',
        };
    }

    public function trafficLightLabel(?CarbonInterface $reference = null): string
    {
        return match ($this->trafficLightClass($reference)) {
            'appointment-cancelled' => 'Cita cancelada',
            'appointment-confirmed' => 'Cita confirmada',
            'appointment-pending' => 'Pendiente, faltan mas de 24 horas',
            'appointment-urgent-light' => 'Pendiente, faltan menos de 24 horas',
            'appointment-urgent-medium' => 'Pendiente, faltan menos de 12 horas',
            default => 'Pendiente, faltan menos de 6 horas',
        };
    }
}
