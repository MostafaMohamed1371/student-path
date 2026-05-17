<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRoute extends Model
{
    protected $fillable = [
        'school_id',
        'driver_id',
        'name',
        'shift_period',
        'trip_type',
        'start_address',
        'start_latitude',
        'start_longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_latitude' => 'float',
            'start_longitude' => 'float',
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

    public function routeStudents(): HasMany
    {
        return $this->hasMany(TransportRouteStudent::class)->orderBy('sort_order');
    }
}
