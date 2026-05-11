<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripHistoryStudent extends Model
{
    protected $fillable = [
        'trip_history_id',
        'student_id',
        'sort_order',
        'status',
        'boarding_time',
        'arrived_at',
    ];

    protected function casts(): array
    {
        return [
            'boarding_time' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    public function tripHistory(): BelongsTo
    {
        return $this->belongsTo(TripHistory::class, 'trip_history_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
