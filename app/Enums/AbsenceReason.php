<?php

namespace App\Enums;

enum AbsenceReason: string
{
    case Medical = 'medical';
    case Travel = 'travel';
    case Family = 'family';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::Medical => 'Medical',
            self::Travel => 'Travel',
            self::Family => 'Family circumstances',
            self::Other => 'Other',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Medical => 'صحية',
            self::Travel => 'سفر',
            self::Family => 'ظروف عائلية',
            self::Other => 'أخرى',
        };
    }

    public static function normalize(string $value): ?self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $resolved = self::tryFrom(strtolower($trimmed));
        if ($resolved !== null) {
            return $resolved;
        }

        return match (strtolower($trimmed)) {
            'health', 'sick', 'medical reason' => self::Medical,
            'trip', 'vacation' => self::Travel,
            'family', 'family circumstances', 'family_reason' => self::Family,
            default => self::Other,
        };
    }

    /**
     * @return list<array{code: string, label_en: string, label_ar: string}>
     */
    public static function metaList(): array
    {
        return array_map(
            fn (self $reason): array => [
                'code' => $reason->value,
                'label_en' => $reason->labelEn(),
                'label_ar' => $reason->labelAr(),
            ],
            self::cases(),
        );
    }
}
