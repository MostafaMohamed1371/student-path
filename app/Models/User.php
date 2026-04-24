<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'school_id',
    'image',
    'phone',
    'city',
    'licence_number',
    'votes',
    'rate',
    'is_verified',
    'preferred_language',
    'phone_verified_at',
    'is_active',
    'is_admin',
    'password',
])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'is_verified' => 'boolean',
            'votes' => 'integer',
            'rate' => 'float',
            'password' => 'hashed',
        ];
    }

    public function bus(): HasOne
    {
        return $this->hasOne(Bus::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }
}
