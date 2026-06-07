<?php

namespace App\Services\Driver;

use App\Models\Driver;
use App\Models\User;

/**
 * Keep {@see Driver} aligned with the linked user: name (split for driver columns),
 * school, national phone, city, and licence.
 */
final class UserDriverProfileSynchronizer
{
    /**
     * @param  bool  $markActive  Set driver status to active (e.g. after OTP verify).
     */
    public function syncFromUser(User $user, bool $markActive = false): void
    {
        $user->loadMissing('driver');
        if (! $user->driver instanceof Driver) {
            return;
        }

        $driver = $user->driver;
        $national = substr((string) $user->phone, 3);
        $payload = [
            'primary_phone' => $national,
            'school_id' => $user->school_id,
        ];
        if ($markActive) {
            $payload['status'] = 'active';
        }

        if (filled($user->name)) {
            $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
            $payload['first_name'] = $parts[0] ?? null;
            $payload['father_name'] = $parts[1] ?? null;
            $payload['last_name'] = $parts[2] ?? null;
        } else {
            $payload['first_name'] = null;
            $payload['father_name'] = null;
            $payload['last_name'] = null;
        }

        $payload['residential_address'] = $user->city;
        $payload['license_number'] = $user->licence_number;

        $driver->fill($payload);
        $driver->save();
    }
}
