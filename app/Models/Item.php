<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'unit', 'default_rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
