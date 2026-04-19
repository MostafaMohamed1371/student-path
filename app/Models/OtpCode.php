<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phone
 * @property string $code Hashed OTP (never store plaintext).
 * @property OtpPurpose|string $purpose
 * @property Carbon $expires_at
 * @property Carbon $resend_available_at
 * @property Carbon|null $verified_at
 * @property int $attempts
 * @property int $max_attempts
 */
class OtpCode extends Model
{
    /** @use HasFactory<\Database\Factories\OtpCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'phone',
        'code',
        'purpose',
        'expires_at',
        'resend_available_at',
        'verified_at',
        'attempts',
        'max_attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'resend_available_at' => 'datetime',
            'verified_at' => 'datetime',
            'purpose' => OtpPurpose::class,
        ];
    }

    /**
     * Scope: OTP row is still a candidate for verification (not used, not past expiry).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('verified_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForPhoneAndPurpose(Builder $query, string $phone, OtpPurpose $purpose): Builder
    {
        return $query->where('phone', $phone)->where('purpose', $purpose);
    }
}
