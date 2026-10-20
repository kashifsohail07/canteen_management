<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'notes', 'default_item_name', 'default_unit', 'default_rate',
        'current_balance', 'opening_balance', 'opening_balance_locked', 'is_active', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'default_rate' => 'decimal:2',
            'opening_balance_locked' => 'boolean',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /** True when this vendor always uses the same per-piece rate (e.g. Samosay @ Rs. 20). */
    public function hasFixedRate(): bool
    {
        return $this->default_rate !== null && bccomp((string) $this->default_rate, '0', 2) > 0;
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
