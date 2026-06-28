<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityResource extends Model
{
    protected $fillable = ['clinic_id', 'name', 'capacity', 'is_active'];
    protected $casts = ['capacity' => 'integer', 'is_active' => 'boolean'];

    public function clinic(): BelongsTo { return $this->belongsTo(Clinic::class); }
    public function services(): HasMany { return $this->hasMany(Service::class); }
}
