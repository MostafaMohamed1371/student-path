<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id',
    'driver_id',
    'trip_type',
    'bus_number',
    'route_title',
    'location',
    'students_count',
    'distance_km',
    'start_time',
    'end_time',
    'final_lat',
    'final_lng',
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
            'final_lat' => 'float',
            'final_lng' => 'float',
            'distance_km' => 'float',
            'students_count' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function tripHistoryStudents(): HasMany
    {
        return $this->hasMany(TripHistoryStudent::class, 'trip_history_id')->orderBy('sort_order')->orderBy('id');
    }

    public function delayAlerts(): HasMany
    {
        return $this->hasMany(DelayAlert::class, 'trip_history_id')->latest('id');
    }

    public function sosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class, 'trip_history_id')->latest('id');
    }
}

