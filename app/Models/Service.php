<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'clinic_id',
        'facility_resource_id',
        'resource_units',
        'name',
        'duration_minutes',
        'price_cents',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'resource_units' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) return null;
        if (str_starts_with($this->image_path, 'sample:')) return asset('images/service-samples/'.substr($this->image_path, 7));
        return asset('storage/'.$this->image_path);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ServiceAddon::class)->orderBy('name');
    }

    public function activeAddons(): HasMany
    {
        return $this->hasMany(ServiceAddon::class)->where('is_active', true)->orderBy('name');
    }

    public function facilityResource(): BelongsTo
    {
        return $this->belongsTo(FacilityResource::class);
    }
}
