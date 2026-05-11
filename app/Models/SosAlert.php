<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlert extends Model
{
    protected $fillable = [
        'trip_history_id',
        'driver_id',
        'user_id',
        'emergency_type',
        'status',
        'driver_lat',
        'driver_lng',
        'triggered_at',
        'stopped_at',
        'stop_reason',
        'final_lat',
        'final_lng',
    ];

    protected function casts(): array
    {
        return [
            'driver_lat' => 'float',
            'driver_lng' => 'float',
            'final_lat' => 'float',
            'final_lng' => 'float',
            'triggered_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function tripHistory(): BelongsTo
    {
        return $this->belongsTo(TripHistory::class, 'trip_history_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
