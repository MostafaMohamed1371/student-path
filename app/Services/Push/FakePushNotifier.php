<?php

namespace App\Services\Push;

use App\Contracts\Push\PushNotifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Logs push payloads instead of calling Firebase (local dev / missing credentials).
 */
final class FakePushNotifier implements PushNotifier
{
    public function notifyUser(User|int $user, string $title, ?string $body = null, array $data = []): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        Log::info('FCM push (fake)', [
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
