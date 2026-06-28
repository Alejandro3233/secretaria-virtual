<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashCampaign extends Model
{
    protected $fillable = [
        'clinic_id', 'service_id', 'created_by', 'name', 'discount_percent',
        'original_price_cents', 'discounted_price_cents', 'segment', 'channels',
        'subject', 'message', 'expires_at', 'status', 'sent_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'ended_at' => 'datetime',
            'discount_percent' => 'integer',
            'original_price_cents' => 'integer',
            'discounted_price_cents' => 'integer',
        ];
    }

    public function clinic(): BelongsTo { return $this->belongsTo(Clinic::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function recipients(): HasMany { return $this->hasMany(FlashCampaignRecipient::class); }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->ended_at && $this->expires_at?->isFuture();
    }
}
