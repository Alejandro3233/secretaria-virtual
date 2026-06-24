<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'loyalty_level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'loyalty_level' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(ClientPreference::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function initials(): string
    {
        $firstWords = preg_split('/\s+/u', trim((string) $this->first_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lastWords = preg_split('/\s+/u', trim((string) $this->last_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $firstWords[0] ?? '';
        $last = $lastWords[0] ?? (count($firstWords) > 1 ? end($firstWords) : '');
        $initials = Str::substr($first, 0, 1).Str::substr((string) $last, 0, 1);

        return Str::upper($initials ?: 'CL');
    }
}
