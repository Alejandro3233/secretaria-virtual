<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentPayment extends Model
{
    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'client_id',
        'user_id',
        'amount_cents',
        'currency',
        'method',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'checkout_url',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
