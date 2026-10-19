<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCalendar extends Model
{
    protected $table = 'school_calendar';

    protected $fillable = ['date', 'day_type', 'label', 'is_working_day', 'notes'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_working_day' => 'boolean',
        ];
    }
}
