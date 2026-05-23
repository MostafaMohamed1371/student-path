<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmTopicSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'topic',
        'trip_history_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tripHistory(): BelongsTo
    {
        return $this->belongsTo(TripHistory::class, 'trip_history_id');
    }
}
