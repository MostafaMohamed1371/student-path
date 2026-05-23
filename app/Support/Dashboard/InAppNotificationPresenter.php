<?php

namespace App\Support\Dashboard;

use App\Models\InAppNotification;

final class InAppNotificationPresenter
{
    /**
     * @return list<string>
     */
    public static function filterableTypes(): array
    {
        $fromConfig = array_keys(config('notification_preferences.push_type_map', []));

        return array_values(array_unique(array_merge($fromConfig, [
            'CHAT_MESSAGE',
            'DELAY_ALERT',
            'SOS_TRIGGERED',
        ])));
    }

    public static function dataType(?InAppNotification $notification): ?string
    {
        $data = $notification?->data;

        if (! is_array($data)) {
            return null;
        }

        $type = $data['type'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    public static function tripReference(?InAppNotification $notification): ?string
    {
        $data = $notification?->data;

        if (! is_array($data)) {
            return null;
        }

        foreach (['trip_id', 'tripId'] as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return 'TRP-'.$value;
            }
        }

        if (isset($data['trip_history_id']) && (is_int($data['trip_history_id']) || ctype_digit((string) $data['trip_history_id']))) {
            return 'TRP-'.$data['trip_history_id'];
        }

        return null;
    }

    public static function typeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '—';
        }

        $types = __('dashboard.notification_types');

        if (is_array($types) && isset($types[$type]) && is_string($types[$type])) {
            return $types[$type];
        }

        return $type;
    }
}
