<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = [
        'clinic_id',
        'name',
        'sku',
        'category',
        'current_stock',
        'minimum_stock',
        'unit',
        'cost_cents',
        'sale_price_cents',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->minimum_stock;
    }
}
