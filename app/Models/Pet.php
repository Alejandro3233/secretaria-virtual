<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pet extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'species',
        'breed',
        'birthdate',
        'weight',
        'sex',
        'medical_notes',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'weight' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
