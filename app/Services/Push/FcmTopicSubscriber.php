<?php

namespace App\Services\Push;

use App\Contracts\Push\FcmTopicSubscriber as FcmTopicSubscriberContract;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Throwable;

final class FcmTopicSubscriber implements FcmTopicSubscriberContract
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    public function subscribe(User $user, string $topic, ?int $tripHistoryId = null, ?array $tokens = null): void
    {
        $tokens = $this->resolveTokens($user, $tokens);
        if ($tokens === []) {
            return;
        }

        try {
            $this->messaging->subscribeToTopic($topic, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM topic subscribe failed', [
                'user_id' => $user->id,
                'topic' => $topic,
                'trip_history_id' => $tripHistoryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function unsubscribe(User $user, string $topic, ?array $tokens = null): void
    {
        $tokens = $this->resolveTokens($user, $tokens);
        if ($tokens === []) {
            return;
        }

        try {
            $this->messaging->unsubscribeFromTopic($topic, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM topic unsubscribe failed', [
                'user_id' => $user->id,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>|null  $tokens
     * @return list<string>
     */
    private function resolveTokens(User $user, ?array $tokens): array
    {
        if ($tokens !== null && $tokens !== []) {
            return array_values(array_filter(array_map('trim', $tokens), static fn (string $t): bool => $t !== ''));
        }

        return UserFcmToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->filter(static fn ($token): bool => is_string($token) && $token !== '')
            ->values()
            ->all();
    }
}
