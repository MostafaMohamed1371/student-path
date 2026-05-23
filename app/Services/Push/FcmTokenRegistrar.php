<?php

namespace App\Services\Push;

use App\Models\User;
use App\Models\UserFcmToken;

final class FcmTokenRegistrar
{
    public function register(User $user, string $token, ?string $platform = null, ?string $deviceId = null): UserFcmToken
    {
        $normalizedToken = trim($token);
        $normalizedPlatform = $platform !== null ? strtolower(trim($platform)) : null;
        $normalizedDeviceId = $deviceId !== null ? trim($deviceId) : null;

        if ($normalizedDeviceId === '') {
            $normalizedDeviceId = null;
        }

        $row = UserFcmToken::query()->firstOrNew(['token' => $normalizedToken]);
        $row->user_id = $user->id;
        $row->platform = $normalizedPlatform;
        $row->device_id = $normalizedDeviceId;
        $row->last_used_at = now();
        $row->save();

        return $row;
    }

    public function unregister(User $user, string $token): bool
    {
        return UserFcmToken::query()
            ->where('user_id', $user->id)
            ->where('token', trim($token))
            ->delete() > 0;
    }
}
