<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportRouteStudent extends Model
{
    protected $fillable = [
        'transport_route_id',
        'student_id',
        'sort_order',
        'distance_from_school_km',
    ];

    protected function casts(): array
    {
        return [
            'distance_from_school_km' => 'float',
        ];
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
