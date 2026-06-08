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
    'work_days',
    'shift_period',
    'work_time_from',
    'work_time_to',
    'evening_work_time_from',
    'evening_work_time_to',
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
            'work_days' => 'array',
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

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function guardians(): HasManyThrough
    {
        return $this->hasManyThrough(
            Guardian::class,
            Student::class,
            'school_id',
            'id',
            'id',
            'guardian_id'
        );
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
