<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'driver_id',
        'name',
        'type',
        'vehicle_model_year',
        'ac_status',
        'city',
        'number',
        'color',
        'capacity',
        'fuel_type',
        'status',
        'annual_status',
        'insurance',
    ];

    protected function casts(): array
    {
        return [
            'annual_status' => 'boolean',
            'insurance' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
