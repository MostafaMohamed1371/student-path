<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use Illuminate\Support\Carbon;

final class NotificationContractMapper
{
    public const TRIP = 'TRIP';

    public const ALERT = 'ALERT';

    public const SCHEDULE = 'SCHEDULE';

    public const WARNING = 'WARNING';

    public const LOCATION = 'LOCATION';

    /**
     * @return list<string>
     */
    public static function contractTypes(): array
    {
        return [
            self::TRIP,
            self::ALERT,
            self::SCHEDULE,
            self::WARNING,
            self::LOCATION,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function toContractType(?array $data): string
    {
        $internal = is_array($data) ? ($data['type'] ?? null) : null;
        if (! is_string($internal) || $internal === '') {
            return (string) config('notifications.contract_type_default', self::WARNING);
        }

        $key = strtoupper($internal);
        $map = config('notifications.contract_type_map', []);

        if (is_array($map) && isset($map[$key]) && is_string($map[$key])) {
            return $map[$key];
        }

        $allowed = self::contractTypes();
        if (in_array($key, $allowed, true)) {
            return $key;
        }

        return (string) config('notifications.contract_type_default', self::WARNING);
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     body: string,
     *     type: string,
     *     isRead: bool,
     *     createdAt: string
     * }
     */
    public static function toContractItem(InAppNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $created = $notification->created_at instanceof Carbon
            ? $notification->created_at->clone()->utc()
            : now()->utc();

        $item = [
            'id' => (string) $notification->id,
            'title' => (string) $notification->title,
            'body' => (string) ($notification->body ?? ''),
            'type' => self::toContractType($data),
            'isRead' => $notification->read_at !== null,
            'createdAt' => self::formatCreatedAtUtc($created),
        ];

        return array_intersect_key(
            $item,
            array_flip(NotificationContractListService::ITEM_KEYS),
        );
    }

    /**
     * FCM data payload fields required by the mobile contract.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function formatCreatedAtUtc(Carbon $created): string
    {
        return $created->clone()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public static function fcmDataPayload(
        int $notificationId,
        string $title,
        ?string $body,
        array $data,
    ): array {
        return [
            'notificationId' => (string) $notificationId,
            'type' => self::toContractType($data),
            'title' => $title,
            'body' => (string) ($body ?? ''),
        ];
    }
}
