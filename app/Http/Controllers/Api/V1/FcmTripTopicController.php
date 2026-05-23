<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TripHistory;
use App\Services\Push\FcmTopicSubscriptionService;
use App\Services\Push\TripTopicNamer;
use App\Services\Trips\DriverTripModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTripTopicController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly FcmTopicSubscriptionService $subscriptions,
        private readonly DriverTripModuleService $tripIds,
        private readonly TripTopicNamer $topicNamer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->subscriptions->listForUser($request->user())->map(static function ($row): array {
            return [
                'topic' => $row->topic,
                'trip_history_id' => $row->trip_history_id,
                'trip' => $row->tripHistory ? [
                    'id' => $row->tripHistory->id,
                    'route_title' => $row->tripHistory->route_title,
                    'status' => $row->tripHistory->status,
                    'start_time' => $row->tripHistory->start_time?->toIso8601String(),
                ] : null,
                'subscribed_at' => $row->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return $this->parentSuccess([
            'topic_template' => (string) config('realtime.channel_prefix', 'trip_').'{tripId}',
            'items' => $items,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trip_id' => ['required', 'string', 'max:64'],
            'token' => ['nullable', 'string', 'min:32', 'max:512'],
        ]);

        $trip = $this->resolveTrip((string) $validated['trip_id']);
        if (! $trip) {
            return $this->parentError('Trip not found.', null, 404);
        }

        try {
            $row = $this->subscriptions->subscribeToTrip(
                $request->user(),
                $trip,
                $validated['token'] ?? null,
            );
        } catch (\InvalidArgumentException) {
            return $this->parentError('forbidden', null, 403);
        }

        return $this->parentSuccess([
            'topic' => $row->topic,
            'trip_id' => $this->tripIds->externalTripId($trip),
            'trip_history_id' => $trip->id,
        ], 'Subscribed to trip tracking topic');
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trip_id' => ['required', 'string', 'max:64'],
            'token' => ['nullable', 'string', 'min:32', 'max:512'],
        ]);

        $trip = $this->resolveTrip((string) $validated['trip_id']);
        if (! $trip) {
            return $this->parentError('Trip not found.', null, 404);
        }

        try {
            $removed = $this->subscriptions->unsubscribeFromTrip(
                $request->user(),
                $trip,
                $validated['token'] ?? null,
            );
        } catch (\InvalidArgumentException) {
            return $this->parentError('forbidden', null, 403);
        }

        if (! $removed) {
            return $this->parentError('Topic subscription not found', null, 404);
        }

        return $this->parentSuccess([
            'topic' => $this->topicNamer->topicForTrip($trip),
            'trip_id' => $this->tripIds->externalTripId($trip),
        ], 'Unsubscribed from trip tracking topic');
    }

    private function resolveTrip(string $tripId): ?TripHistory
    {
        $pk = $this->tripIds->parseTripPublicId($tripId);
        if ($pk === null) {
            return null;
        }

        return TripHistory::query()->find($pk);
    }
}
