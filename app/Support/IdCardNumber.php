<?php

namespace App\Support;

final class IdCardNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }
}
