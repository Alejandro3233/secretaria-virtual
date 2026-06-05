<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPreference extends Model
{
    protected $fillable = [
        'client_id',
        'hair_type',
        'preferred_stylist',
        'color_formula',
        'allergies',
        'beauty_notes',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
