<?php

namespace App\Contracts\Push;

use App\Models\User;

/**
 * Sends mobile push notifications (FCM) to a user's registered device tokens.
 */
interface PushNotifier
{
    /**
     * @param  array<string, mixed>  $data  Deep-link payload (mirrors in_app_notifications.data)
     */
    public function notifyUser(User|int $user, string $title, ?string $body = null, array $data = []): void;
}
