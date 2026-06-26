<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StylistVacation extends Model
{
    protected $fillable = [
        'stylist_id',
        'starts_on',
        'ends_on',
        'reason',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function stylist(): BelongsTo
    {
        return $this->belongsTo(Stylist::class);
    }
}
