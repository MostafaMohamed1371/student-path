<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeLocation extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'formatted_address',
        'district_area',
        'nearest_landmark',
        'place_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
