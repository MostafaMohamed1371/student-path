<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Support\ParentContext;
use Illuminate\Validation\ValidationException;

final class ChatParentDriverConversationStarter
{
    public function __construct(
        private readonly ChatParentDriverConversationProvisioner $provisioner,
    ) {}

    /**
     * @param  array{trip_request_id?: int|null, driver_id?: int|null, parent_user_id?: int|null}  $input
     *
     * @throws ValidationException
     */
    public function start(User $actor, array $input): ChatParentDriverConversationResult
    {
        if ($actor->isChatStaff()) {
            throw ValidationException::withMessages([
                'conversation' => ['School staff cannot start a parent-driver chat from the app API.'],
            ]);
        }

        $tripRequestId = isset($input['trip_request_id']) ? (int) $input['trip_request_id'] : 0;
        $driverId = isset($input['driver_id']) ? (int) $input['driver_id'] : 0;
        $parentUserId = isset($input['parent_user_id']) ? (int) $input['parent_user_id'] : 0;

        if ($tripRequestId > 0) {
            return $this->startFromTripRequest($actor, $tripRequestId);
        }

        $actor->loadMissing('driver');

        if ($actor->driver) {
            return $this->startAsDriver($actor, $parentUserId, $driverId);
        }

        return $this->startAsParent($actor, $driverId, $parentUserId);
    }

    /**
     * @throws ValidationException
     */
    private function startFromTripRequest(User $actor, int $tripRequestId): ChatParentDriverConversationResult
    {
        $tripRequest = TripRequest::query()
            ->with(['user', 'driver.user', 'student'])
            ->find($tripRequestId);

        if ($tripRequest === null) {
            throw ValidationException::withMessages([
                'trip_request_id' => ['Trip request not found.'],
            ]);
        }

        if (! in_array($tripRequest->status, ['pending', 'accepted'], true)) {
            throw ValidationException::withMessages([
                'trip_request_id' => ['Chat is only available for pending or accepted trip requests.'],
            ]);
        }

        $this->assertActorLinkedToTripRequest($actor, $tripRequest);

        $parentUserId = (int) $tripRequest->user_id;
        $driverUserId = (int) ($tripRequest->driver?->user_id ?? 0);

        if ($driverUserId <= 0) {
            throw ValidationException::withMessages([
                'trip_request_id' => ['The assigned driver does not have a login account yet.'],
            ]);
        }

        $schoolId = (int) ($tripRequest->student?->school_id ?? $tripRequest->driver?->school_id ?? 0);
        $existing = ChatConversation::query()
            ->where('conversation_type', ChatConversation::TYPE_PARENT_DRIVER)
            ->where('user_id', $parentUserId)
            ->where('participant_id', $driverUserId)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->exists();

        $conversation = $this->provisioner->ensureBetweenParentAndDriver(
            $parentUserId,
            $driverUserId,
            $schoolId > 0 ? $schoolId : null,
            (int) $tripRequest->id,
        );

        if ($conversation === null) {
            throw ValidationException::withMessages([
                'conversation' => ['Unable to start parent-driver chat.'],
            ]);
        }

        return new ChatParentDriverConversationResult($conversation, ! $existing);
    }

    /**
     * @throws ValidationException
     */
    private function startAsParent(User $parent, int $driverId, int $parentUserId): ChatParentDriverConversationResult
    {
        if ($parentUserId > 0 && $parentUserId !== (int) $parent->id) {
            throw ValidationException::withMessages([
                'parent_user_id' => ['Parents can only start chats for their own account.'],
            ]);
        }

        if ($driverId <= 0) {
            throw ValidationException::withMessages([
                'driver_id' => ['driver_id is required when starting chat as a parent.'],
            ]);
        }

        $driver = Driver::query()->with('user')->find($driverId);
        if ($driver === null || $driver->user_id === null) {
            throw ValidationException::withMessages([
                'driver_id' => ['Driver not found or missing login account.'],
            ]);
        }

        $tripRequest = $this->findLinkingTripRequest((int) $parent->id, (int) $driver->id);
        if ($tripRequest === null) {
            throw ValidationException::withMessages([
                'driver_id' => ['Create a trip request with this driver before starting chat.'],
            ]);
        }

        return $this->startFromTripRequest($parent, (int) $tripRequest->id);
    }

    /**
     * @throws ValidationException
     */
    private function startAsDriver(User $driverUser, int $parentUserId, int $driverId): ChatParentDriverConversationResult
    {
        if ($driverId > 0 && $driverId !== (int) $driverUser->driver->id) {
            throw ValidationException::withMessages([
                'driver_id' => ['Drivers can only start chats for their own profile.'],
            ]);
        }

        if ($parentUserId <= 0) {
            throw ValidationException::withMessages([
                'parent_user_id' => ['parent_user_id is required when starting chat as a driver.'],
            ]);
        }

        $tripRequest = $this->findLinkingTripRequest($parentUserId, (int) $driverUser->driver->id);
        if ($tripRequest === null) {
            throw ValidationException::withMessages([
                'parent_user_id' => ['No pending or accepted trip request exists with this parent.'],
            ]);
        }

        return $this->startFromTripRequest($driverUser, (int) $tripRequest->id);
    }

    private function findLinkingTripRequest(int $parentUserId, int $driverId): ?TripRequest
    {
        return TripRequest::query()
            ->where('user_id', $parentUserId)
            ->where('driver_id', $driverId)
            ->whereIn('status', ['pending', 'accepted'])
            ->latest('id')
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function assertActorLinkedToTripRequest(User $actor, TripRequest $tripRequest): void
    {
        $actor->loadMissing('driver');

        if ((int) $tripRequest->user_id === (int) $actor->id) {
            return;
        }

        if ($actor->driver && (int) $tripRequest->driver_id === (int) $actor->driver->id) {
            return;
        }

        throw ValidationException::withMessages([
            'trip_request_id' => ['You are not allowed to access this trip request.'],
        ]);
    }
}

final class ChatParentDriverConversationResult
{
    public function __construct(
        public readonly ChatConversation $conversation,
        public readonly bool $created,
    ) {}
}
