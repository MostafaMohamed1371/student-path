<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'driver_id',
        'transport_route_id',
        'start_date',
        'end_date',
        'reason',
        'notes',
        'driver_notified_at',
        'school_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'driver_notified_at' => 'datetime',
            'school_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }
}
