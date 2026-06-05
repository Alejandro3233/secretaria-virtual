<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'clinic_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'notification_preference',
        'notes',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(ClientPreference::class);
    }
}
