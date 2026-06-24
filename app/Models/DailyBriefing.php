<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyBriefing extends Model
{
    protected $fillable = ['clinic_id', 'user_id', 'briefing_date', 'message', 'played_at'];

    protected $casts = [
        'briefing_date' => 'date',
        'played_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
