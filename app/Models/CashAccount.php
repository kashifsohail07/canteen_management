<?php

namespace App\Models;

use App\Enums\CashAccountCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    protected $fillable = ['code', 'name', 'balance'];

    protected function casts(): array
    {
        return [
            'code' => CashAccountCode::class,
            'balance' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }
}
