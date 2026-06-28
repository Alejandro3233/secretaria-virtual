<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashCampaignRecipient extends Model
{
    protected $fillable = [
        'flash_campaign_id', 'client_id', 'token', 'email', 'phone', 'email_status',
        'sms_status', 'email_provider_id', 'sms_provider_id', 'email_error', 'sms_error',
        'email_sent_at', 'sms_sent_at', 'opened_at',
    ];

    protected function casts(): array
    {
        return ['email_sent_at' => 'datetime', 'sms_sent_at' => 'datetime', 'opened_at' => 'datetime'];
    }

    public function campaign(): BelongsTo { return $this->belongsTo(FlashCampaign::class, 'flash_campaign_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
}
