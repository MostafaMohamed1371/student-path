<?php

use App\Models\ChatConversation;
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

    $user->loadMissing('driver');
    if ($user->driver && (int) $user->driver->id === (int) ($trip->driver_id ?? 0)) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
}, ['guards' => ['sanctum', 'web']]);

/*
| Support live chat (Pusher): private channel "chat.{conversationId}".
*/
Broadcast::channel('chat.{conversationId}', function ($user, string $conversationId) {
    $conversation = ChatConversation::query()->find($conversationId);
    if (! $conversation || ! $conversation->canBeAccessedBy($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'is_staff' => (bool) $user->is_admin,
    ];
}, ['guards' => ['sanctum', 'web']]);
