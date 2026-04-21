<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
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
}
