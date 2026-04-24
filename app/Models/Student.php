<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
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
}
