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
    'start_address',
    'start_latitude',
    'start_longitude',
    'start_district_id',
    'start_area_id',
    'start_neighborhood_id',
    'students_count',
    'distance_km',
    'start_time',
    'driver_started_at',
    'end_time',
    'final_lat',
    'final_lng',
    'status',
    'is_recurring_template',
    'auto_schedule_work_days',
    'recurring_template_id',
    'note',
    'students_preview',
])]
class TripHistory extends Model
{
    protected function casts(): array
    {
        return [
            'students_preview' => 'array',
            'is_recurring_template' => 'boolean',
            'auto_schedule_work_days' => 'boolean',
            'start_time' => 'datetime',
            'driver_started_at' => 'datetime',
            'end_time' => 'datetime',
            'final_lat' => 'float',
            'final_lng' => 'float',
            'start_latitude' => 'float',
            'start_longitude' => 'float',
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

    public function tripFeedbacks(): HasMany
    {
        return $this->hasMany(TripFeedback::class, 'trip_history_id')->latest('id');
    }

    public function tripDriverRatings(): HasMany
    {
        return $this->hasMany(TripDriverRating::class, 'trip_history_id')->latest('id');
    }
}

