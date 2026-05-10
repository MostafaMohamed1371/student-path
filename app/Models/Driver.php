<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'school_id',
    'user_id',
    'first_name',
    'father_name',
    'grandfather_name',
    'last_name',
    'age',
    'id_card_number',
    'license_number',
    'primary_phone',
    'emergency_phone',
    'residential_address',
    'status',
    'monthly_subscription_price',
    'id_card_image',
    'license_image',
    'non_conviction_certificate',
])]
class Driver extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_subscription_price' => 'integer',
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

    public function bus(): HasOne
    {
        return $this->hasOne(Bus::class);
    }
}
