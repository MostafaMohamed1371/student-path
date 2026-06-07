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
        $payload = [];

        if ($markActive) {
            $payload['status'] = 'active';
            $payload['primary_phone'] = substr((string) $user->phone, 3);
            $payload['school_id'] = $user->school_id;
            $payload = array_merge($payload, $this->namePayload($user));
            $payload['residential_address'] = $user->city;
            $payload['license_number'] = $user->licence_number;
        } else {
            if ($user->wasChanged('phone')) {
                $payload['primary_phone'] = substr((string) $user->phone, 3);
            }

            if ($user->wasChanged('school_id')) {
                $payload['school_id'] = $user->school_id;
            }

            if ($user->wasChanged('name')) {
                $payload = array_merge($payload, $this->namePayload($user));
            }

            if ($user->wasChanged('city')) {
                $payload['residential_address'] = $user->city;
            }

            if ($user->wasChanged('licence_number')) {
                $payload['license_number'] = $user->licence_number;
            }
        }

        if ($payload === []) {
            return;
        }

        $driver->fill($payload);
        $driver->save();
    }

    /**
     * @return array<string, string|null>
     */
    private function namePayload(User $user): array
    {
        if (filled($user->name)) {
            $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];

            return [
                'first_name' => $parts[0] ?? null,
                'father_name' => $parts[1] ?? null,
                'last_name' => $parts[2] ?? null,
            ];
        }

        return [
            'first_name' => null,
            'father_name' => null,
            'last_name' => null,
        ];
    }
}
