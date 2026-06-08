<?php

namespace App\Support;

final class SchoolWorkDay
{
    /** @return list<string> */
    public static function keys(): array
    {
        return [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];
    }

    public static function label(string $key): string
    {
        return __('dashboard.work_day_'.$key);
    }

    /** @param  list<string>|null  $selected */
    public static function isSelected(?array $selected, string $key): bool
    {
        return in_array($key, $selected ?? [], true);
    }

    public static function formatTimeForInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $time = (string) $value;

        return strlen($time) >= 5 ? substr($time, 0, 5) : $time;
    }
}
