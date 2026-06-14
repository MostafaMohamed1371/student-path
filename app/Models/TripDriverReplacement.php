<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'template_trip_id',
    'service_date',
    'replacement_driver_id',
])]
class TripDriverReplacement extends Model
{
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }

    public function templateTrip(): BelongsTo
    {
        return $this->belongsTo(TripHistory::class, 'template_trip_id');
    }

    public function replacementDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'replacement_driver_id');
    }
}
