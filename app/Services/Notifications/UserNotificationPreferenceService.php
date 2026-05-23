<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotificationPreference;

final class UserNotificationPreferenceService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User|int $user): array
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        $defaults = $this->defaultPreferences();

        $row = UserNotificationPreference::query()->where('user_id', $userId)->first();
        if (! $row || ! is_array($row->preferences)) {
            return $defaults;
        }

        return $this->mergePreferences($defaults, $row->preferences);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(User $user, array $input): array
    {
        $merged = $this->mergePreferences($this->forUser($user), $input);

        UserNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['preferences' => $this->extractStorablePreferences($merged)],
        );

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $data  in_app_notifications.data
     */
    public function allowsPush(User|int $user, array $data): bool
    {
        $type = isset($data['type']) ? (string) $data['type'] : '';
        if ($type === '') {
            return true;
        }

        $map = config('notification_preferences.push_type_map', []);
        if (! isset($map[$type]) || ! is_array($map[$type]) || count($map[$type]) !== 2) {
            return true;
        }

        [$group, $key] = $map[$type];
        $prefs = $this->forUser($user);

        return (bool) data_get($prefs, "{$group}.{$key}", true);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultPreferences(): array
    {
        return config('notification_preferences.defaults', []);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergePreferences(array $defaults, array $overrides): array
    {
        $merged = array_replace_recursive($defaults, $overrides);

        foreach ($defaults as $group => $keys) {
            if (! is_array($keys)) {
                continue;
            }
            foreach (array_keys($keys) as $key) {
                $merged[$group][$key] = (bool) ($merged[$group][$key] ?? $keys[$key]);
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function extractStorablePreferences(array $merged): array
    {
        $out = [];
        foreach ($this->defaultPreferences() as $group => $keys) {
            if (! is_array($keys)) {
                continue;
            }
            foreach (array_keys($keys) as $key) {
                $out[$group][$key] = (bool) ($merged[$group][$key] ?? $keys[$key]);
            }
        }

        return $out;
    }
}
