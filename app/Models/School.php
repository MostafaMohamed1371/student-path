<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'name_ar',
    'name_en',
    'province',
    'district',
    'address',
    'latitude',
    'longitude',
    'status',
    'principal_name',
    'admin_phone',
    'authorized_person_name',
    'authorized_person_phone',
    'notes',
    'attachment',
])]
class School extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function buses(): HasManyThrough
    {
        return $this->hasManyThrough(
            Bus::class,
            Driver::class,
            'school_id',
            'driver_id',
            'id',
            'id'
        );
    }
}
