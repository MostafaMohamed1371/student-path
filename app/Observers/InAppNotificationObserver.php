<?php

namespace App\Observers;

use App\Contracts\Push\PushNotifier;
use App\Models\InAppNotification;
use App\Services\Notifications\NotificationContractMapper;
use App\Services\Notifications\UserNotificationPreferenceService;

class InAppNotificationObserver
{
    public function __construct(
        private readonly PushNotifier $pushNotifier,
        private readonly UserNotificationPreferenceService $preferences,
    ) {}

    public function created(InAppNotification $notification): void
    {
        if (! config('fcm.enabled', false)) {
            return;
        }

        $data = is_array($notification->data) ? $notification->data : [];
        if (! $this->preferences->allowsPush((int) $notification->user_id, $data)) {
            return;
        }

        $title = (string) $notification->title;
        $body = $notification->body;

        $fcmData = array_merge(
            $data,
            NotificationContractMapper::fcmDataPayload(
                (int) $notification->id,
                $title,
                $body,
                $data,
            ),
        );

        $this->pushNotifier->notifyUser(
            (int) $notification->user_id,
            $title,
            $body,
            $fcmData,
        );
    }
}
