<?php

namespace App\Services\Push;

use App\Models\TripHistory;
use App\Models\User;
use App\Support\ParentContext;

final class TripTrackingAuthorization
{
    public function canSubscribe(User $user, TripHistory $trip): bool
    {
        if ($user->is_admin) {
            if ($user->school_id === null) {
                return true;
            }

            return (int) $user->school_id === (int) $trip->school_id;
        }

        if ($user->school_id !== null && (int) $user->school_id === (int) $trip->school_id) {
            return true;
        }

        $user->loadMissing('driver');
        if ($user->driver && (int) $user->driver->id === (int) ($trip->driver_id ?? 0)) {
            return true;
        }

        $studentIds = ParentContext::studentIdsFor($user);
        if ($studentIds === []) {
            return false;
        }

        return $trip->tripHistoryStudents()
            ->whereIn('student_id', $studentIds)
            ->exists();
    }
}
