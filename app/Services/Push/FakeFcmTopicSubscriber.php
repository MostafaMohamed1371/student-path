<?php

namespace App\Services\Push;

use App\Contracts\Push\FcmTopicSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class FakeFcmTopicSubscriber implements FcmTopicSubscriber
{
    public function subscribe(User $user, string $topic, ?int $tripHistoryId = null, ?array $tokens = null): void
    {
        Log::info('FCM topic subscribe (fake)', [
            'user_id' => $user->id,
            'topic' => $topic,
            'trip_history_id' => $tripHistoryId,
            'token_count' => $tokens === null ? 'all' : count($tokens),
        ]);
    }

    public function unsubscribe(User $user, string $topic, ?array $tokens = null): void
    {
        Log::info('FCM topic unsubscribe (fake)', [
            'user_id' => $user->id,
            'topic' => $topic,
            'token_count' => $tokens === null ? 'all' : count($tokens),
        ]);
    }
}
