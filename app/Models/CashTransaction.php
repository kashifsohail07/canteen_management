<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $fillable = [
        'cash_account_id', 'daily_session_id', 'direction', 'amount', 'transaction_type',
        'reference_type', 'reference_id', 'balance_after', 'notes', 'created_by', 'recorded_at',
        'idempotency_key', 'is_reversed',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'recorded_at' => 'datetime',
            'is_reversed' => 'boolean',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
