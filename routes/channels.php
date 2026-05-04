<?php

use App\Models\TripHistory;
use App\Services\Trips\StudentTripStatusResolver;
use App\Support\ParentContext;
use Illuminate\Support\Facades\Broadcast;

/*
| Realtime (PDF): subscribe to trip updates. Laravel Echo: private channel "trip.{id}".
| Firebase / other clients: topic "{channel_prefix}{id}" from config/realtime.php.
*/
Broadcast::channel('trip.{tripHistoryId}', function ($user, string $tripHistoryId) {
    $trip = TripHistory::query()->find($tripHistoryId);
    if (! $trip) {
        return false;
    }

    if ($user->is_admin) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    $resolver = app(StudentTripStatusResolver::class);
    foreach (ParentContext::studentIdsFor($user) as $studentId) {
        if ($resolver->tripIncludesStudent($trip, $studentId)) {
            return ['id' => $user->id, 'name' => $user->name];
        }
    }

    if ($user->scopingSchoolId() !== null && (int) $trip->school_id === (int) $user->scopingSchoolId()) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
});
