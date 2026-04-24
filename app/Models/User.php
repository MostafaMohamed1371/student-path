<?php

namespace App\Models;

use App\Services\Driver\UserDriverProfileSynchronizer;
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

    protected static function booted(): void
    {
        parent::booted();

        self::updated(function (User $user) {
            if (! $user->wasChanged(['name', 'school_id', 'phone', 'city', 'licence_number'])) {
                return;
            }
            app(UserDriverProfileSynchronizer::class)->syncFromUser($user, false);
        });
    }

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

    /**
     * For non-admin dashboard scoping: use {@see $school_id} when set; otherwise the linked
     * driver's school (e.g. accounts created only via OTP + driver).
     */
    public function scopingSchoolId(): ?int
    {
        if ($this->school_id) {
            return (int) $this->school_id;
        }
        $this->loadMissing('driver');
        if ($this->driver?->school_id) {
            return (int) $this->driver->school_id;
        }

        return null;
    }
}
