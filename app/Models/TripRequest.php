<?php

namespace App\Models;

use App\Support\ParentContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripRequest extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'driver_id',
        'trip_history_id',
        'status',
        'notes',
        'present_type',
        'moving_point',
        'stop_point',
        'subscribe_price',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'subscribe_price' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function tripHistory(): BelongsTo
    {
        return $this->belongsTo(TripHistory::class, 'trip_history_id');
    }

    /** Parent/guardian name for dashboard and reports (not the assigned driver). */
    public function parentDisplayName(): string
    {
        $this->loadMissing(['user.guardian', 'student.guardian']);

        $candidates = [
            $this->user?->guardian?->full_name,
            $this->user?->name,
            $this->student?->guardian?->full_name,
            $this->student?->guardian_name,
        ];

        if ($this->user instanceof User) {
            $resolvedGuardian = ParentContext::guardian($this->user);
            if ($resolvedGuardian !== null) {
                $candidates[] = $resolvedGuardian->full_name;
            }
        }

        foreach ($candidates as $name) {
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return '—';
    }

    public function parentDisplayPhone(): string
    {
        $this->loadMissing(['user.guardian', 'student.guardian']);

        $candidates = [
            $this->user?->phone,
            $this->user?->guardian?->phone,
            $this->student?->guardian?->phone,
            $this->student?->guardian_primary_phone,
        ];

        if ($this->user instanceof User) {
            $resolvedGuardian = ParentContext::guardian($this->user);
            if ($resolvedGuardian !== null) {
                $candidates[] = $resolvedGuardian->phone;
            }
        }

        foreach ($candidates as $phone) {
            if (is_string($phone) && trim($phone) !== '') {
                return trim($phone);
            }
        }

        return '—';
    }

    public function driverDisplayName(): string
    {
        if (! $this->driver instanceof Driver) {
            return '—';
        }

        $parts = array_filter([
            $this->driver->first_name,
            $this->driver->father_name,
            $this->driver->grandfather_name,
            $this->driver->last_name,
        ], fn (?string $part): bool => is_string($part) && trim($part) !== '');

        $fromDriver = trim(implode(' ', $parts));

        return $fromDriver !== '' ? $fromDriver : '—';
    }
}
