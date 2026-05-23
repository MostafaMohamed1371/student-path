<?php

namespace App\Services\Push;

use App\Contracts\Push\FcmTopicSubscriber;
use App\Models\TripHistory;
use App\Models\User;
use App\Models\UserFcmTopicSubscription;
use Illuminate\Support\Collection;

final class FcmTopicSubscriptionService
{
    public function __construct(
        private readonly FcmTopicSubscriber $subscriber,
        private readonly TripTopicNamer $topicNamer,
        private readonly TripTrackingAuthorization $authorization,
    ) {}

    public function subscribeToTrip(User $user, TripHistory $trip, ?string $token = null): UserFcmTopicSubscription
    {
        if (! $this->authorization->canSubscribe($user, $trip)) {
            throw new \InvalidArgumentException('forbidden');
        }

        $topic = $this->topicNamer->topicForTrip($trip);
        $tokens = $token !== null ? [trim($token)] : null;

        $this->subscriber->subscribe($user, $topic, (int) $trip->id, $tokens);

        return UserFcmTopicSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'topic' => $topic,
            ],
            ['trip_history_id' => $trip->id],
        );
    }

    public function unsubscribeFromTrip(User $user, TripHistory $trip, ?string $token = null): bool
    {
        if (! $this->authorization->canSubscribe($user, $trip)) {
            throw new \InvalidArgumentException('forbidden');
        }

        $topic = $this->topicNamer->topicForTrip($trip);
        $tokens = $token !== null ? [trim($token)] : null;

        $this->subscriber->unsubscribe($user, $topic, $tokens);

        return UserFcmTopicSubscription::query()
            ->where('user_id', $user->id)
            ->where('topic', $topic)
            ->delete() > 0;
    }

    /**
     * @return Collection<int, UserFcmTopicSubscription>
     */
    public function listForUser(User $user): Collection
    {
        return UserFcmTopicSubscription::query()
            ->where('user_id', $user->id)
            ->with('tripHistory:id,route_title,status,start_time')
            ->orderByDesc('id')
            ->get();
    }
}
