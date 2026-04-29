<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'bus_number',
    'route_title',
    'location',
    'students_count',
    'distance_km',
    'start_time',
    'end_time',
    'status',
    'note',
    'students_preview',
])]
class TripHistory extends Model
{
    protected function casts(): array
    {
        return [
            'students_preview' => 'array',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'distance_km' => 'float',
            'students_count' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}

