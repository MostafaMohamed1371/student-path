<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripFeedback extends Model
{
    protected $table = 'trip_feedbacks';

    protected $fillable = [
        'trip_history_id',
        'driver_id',
        'user_id',
        'description',
    ];

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
