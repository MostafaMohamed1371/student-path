<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\TripRequest;

final class ChatParentDriverConversationProvisioner
{
    public function ensureForAcceptedTripRequest(TripRequest $tripRequest): ?ChatConversation
    {
        $tripRequest->loadMissing(['user', 'driver.user', 'student']);

        $parentUserId = (int) ($tripRequest->user_id ?? 0);
        $driverUserId = (int) ($tripRequest->driver?->user_id ?? 0);

        if ($parentUserId <= 0 || $driverUserId <= 0) {
            return null;
        }

        $schoolId = (int) ($tripRequest->student?->school_id ?? $tripRequest->driver?->school_id ?? 0);

        return $this->ensureBetweenParentAndDriver(
            $parentUserId,
            $driverUserId,
            $schoolId > 0 ? $schoolId : null,
            (int) $tripRequest->id,
            $tripRequest->trip_history_id !== null ? (int) $tripRequest->trip_history_id : null,
        );
    }

    public function ensureBetweenParentAndDriver(
        int $parentUserId,
        int $driverUserId,
        ?int $schoolId = null,
        ?int $tripRequestId = null,
        ?int $tripHistoryId = null,
    ): ?ChatConversation {
        if ($parentUserId <= 0 || $driverUserId <= 0) {
            return null;
        }

        $existing = ChatConversation::query()
            ->where('conversation_type', ChatConversation::TYPE_PARENT_DRIVER)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->where('user_id', $parentUserId)
            ->where('participant_id', $driverUserId)
            ->first();

        if ($existing instanceof ChatConversation) {
            $updates = [];
            if ($tripRequestId !== null && $existing->trip_request_id === null) {
                $updates['trip_request_id'] = $tripRequestId;
            }
            if ($tripHistoryId !== null) {
                $updates['trip_history_id'] = $tripHistoryId;
            }
            if ($updates !== []) {
                $existing->forceFill($updates)->save();
            }

            return $existing;
        }

        $closed = ChatConversation::query()
            ->where('conversation_type', ChatConversation::TYPE_PARENT_DRIVER)
            ->where('status', 'closed')
            ->whereNull('deleted_at')
            ->where('user_id', $parentUserId)
            ->where('participant_id', $driverUserId)
            ->orderByDesc('id')
            ->first();

        if ($closed instanceof ChatConversation) {
            $closed->forceFill([
                'status' => 'open',
                'trip_request_id' => $tripRequestId ?? $closed->trip_request_id,
                'trip_history_id' => $tripHistoryId,
                'user_last_read_at' => now(),
                'participant_last_read_at' => now(),
            ])->save();

            return $closed;
        }

        return ChatConversation::query()->create([
            'conversation_type' => ChatConversation::TYPE_PARENT_DRIVER,
            'user_id' => $parentUserId,
            'participant_id' => $driverUserId,
            'school_id' => $schoolId,
            'trip_request_id' => $tripRequestId,
            'trip_history_id' => $tripHistoryId,
            'status' => 'open',
            'user_last_read_at' => now(),
            'participant_last_read_at' => now(),
        ]);
    }
}
