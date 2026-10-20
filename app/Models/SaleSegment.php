<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleSegment extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'is_active', 'is_rent_side'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_rent_side' => 'boolean',
        ];
    }

    /** Rent Side pill on Add Sale — amount goes to rent reserve, not counted as bikri. */
    public function isRentSide(): bool
    {
        return (bool) $this->is_rent_side;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
