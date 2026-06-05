<?php

namespace App\Support;

final class StudentNameComposer
{
    /**
     * Family / last portion taken from the guardian name (last 1–2 words).
     */
    public static function familySuffixFromGuardianName(string $guardianFullName): string
    {
        $parts = self::words($guardianFullName);
        if ($parts === []) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $suffixCount = count($parts) >= 4 ? 2 : 1;

        return implode(' ', array_slice($parts, -$suffixCount));
    }

    public static function compose(string $first, string $second, string $familySuffix): string
    {
        return trim(implode(' ', array_values(array_filter([
            trim($first),
            trim($second),
            trim($familySuffix),
        ], fn (string $part): bool => $part !== ''))));
    }

    /**
     * Student first name (one word) + guardian full name as parent name.
     */
    public static function composeWithGuardianParentName(string $partialStudentName, string $guardianFullName): string
    {
        $words = self::words($partialStudentName);
        $parentName = trim($guardianFullName);
        if ($words === [] || $parentName === '') {
            return trim($partialStudentName);
        }

        return trim(($words[0] ?? '').' '.$parentName);
    }

    /**
     * @return array{first: string, second: string, family_suffix: string}
     */
    public static function split(string $fullName): array
    {
        $parts = self::words($fullName);

        return [
            'first' => $parts[0] ?? '',
            'second' => $parts[1] ?? '',
            'family_suffix' => implode(' ', array_slice($parts, 2)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function words(string $name): array
    {
        return preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
