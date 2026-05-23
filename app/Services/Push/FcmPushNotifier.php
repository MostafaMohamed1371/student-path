<?php

namespace App\Services\Push;

use App\Contracts\Push\PushNotifier;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

final class FcmPushNotifier implements PushNotifier
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    public function notifyUser(User|int $user, string $title, ?string $body = null, array $data = []): void
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        $tokens = UserFcmToken::query()
            ->where('user_id', $userId)
            ->orderByDesc('last_used_at')
            ->pluck('token')
            ->filter(static fn ($token): bool => is_string($token) && $token !== '')
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body ?? ''))
            ->withData($this->stringifyData($data));

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM multicast failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $stale = array_merge($report->unknownTokens(), $report->invalidTokens());
        if ($stale !== []) {
            UserFcmToken::query()->whereIn('token', $stale)->delete();
        }
    }

    /**
     * FCM data payloads require string values.
     *
     * @param  array<string, mixed>  $data
     * @return array<non-empty-string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $stringKey = (string) $key;

            if (is_scalar($value)) {
                $out[$stringKey] = (string) $value;

                continue;
            }

            $out[$stringKey] = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $out;
    }
}
