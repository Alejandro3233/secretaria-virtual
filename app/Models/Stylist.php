<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stylist extends Model
{
    protected $fillable = [
        'clinic_id',
        'service_id',
        'name',
        'email',
        'phone',
        'specialty',
        'work_days',
        'work_starts_at',
        'work_ends_at',
        'is_active',
        'is_internal',
    ];

    protected $casts = [
        'work_days' => 'array',
        'is_active' => 'boolean',
        'is_internal' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(StylistVacation::class);
    }
}
