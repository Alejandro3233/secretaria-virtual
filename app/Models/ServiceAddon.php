<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAddon extends Model
{
    protected $fillable = ['service_id', 'name', 'price_cents', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
