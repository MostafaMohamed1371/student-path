<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'school_id',
    'district_id',
    'area_id',
    'neighborhood_id',
    'guardian_id',
    'full_name',
    'gender',
    'date_of_birth',
    'age',
    'profile_photo',
    'grade',
    'student_phone',
    'guardian_name',
    'guardian_primary_phone',
    'guardian_backup_phone',
    'relationship',
    'district_area',
    'nearest_landmark',
    'latitude',
    'longitude',
    'status',
    'shift_period',
])]
class Student extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'age' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function transportRouteStudent(): HasOne
    {
        return $this->hasOne(TransportRouteStudent::class);
    }
}
