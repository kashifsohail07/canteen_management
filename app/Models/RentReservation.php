<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentReservation extends Model
{
    protected $fillable = [
        'daily_session_id', 'rent_obligation_id', 'sale_id', 'amount', 'notes', 'recorded_at', 'created_by',
        'idempotency_key', 'is_reversed',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recorded_at' => 'datetime',
            'is_reversed' => 'boolean',
        ];
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function rentObligation(): BelongsTo
    {
        return $this->belongsTo(RentObligation::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
