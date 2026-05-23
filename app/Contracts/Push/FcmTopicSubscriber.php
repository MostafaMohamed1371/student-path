<?php

namespace App\Contracts\Push;

use App\Models\User;

interface FcmTopicSubscriber
{
    /**
     * Subscribe the user's FCM registration token(s) to a topic.
     *
     * @param  list<string>|null  $tokens  When null, all tokens for the user are used.
     */
    public function subscribe(User $user, string $topic, ?int $tripHistoryId = null, ?array $tokens = null): void;

    /**
     * @param  list<string>|null  $tokens
     */
    public function unsubscribe(User $user, string $topic, ?array $tokens = null): void;
}
