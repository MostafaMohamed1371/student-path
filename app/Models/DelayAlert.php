<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayAlert extends Model
{
    protected $fillable = [
        'trip_history_id',
        'driver_id',
        'user_id',
        'reason_type',
        'delay_duration_minutes',
        'note',
        'driver_lat',
        'driver_lng',
    ];

    protected function casts(): array
    {
        return [
            'delay_duration_minutes' => 'integer',
            'driver_lat' => 'float',
            'driver_lng' => 'float',
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
