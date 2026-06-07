<?php

namespace App\Enums;

enum StudentAttendanceDayStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';

    public function labelEn(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent => 'غائب',
            self::Late => 'متأخر',
        };
    }

    /** Mobile calendar dot / badge color (matches parent app design). */
    public function colorHex(): string
    {
        return match ($this) {
            self::Present => '#00796B',
            self::Absent => '#D32F2F',
            self::Late => '#5D4037',
        };
    }

    /** Icon key for the mobile calendar legend. */
    public function icon(): string
    {
        return match ($this) {
            self::Present => 'check',
            self::Absent => 'x',
            self::Late => 'clock',
        };
    }

    /**
     * @return list<array{status: string, label_en: string, label_ar: string, color: string, icon: string}>
     */
    public static function legend(): array
    {
        return array_map(
            fn (self $status): array => [
                'status' => $status->value,
                'label_en' => $status->labelEn(),
                'label_ar' => $status->labelAr(),
                'color' => $status->colorHex(),
                'icon' => $status->icon(),
            ],
            self::cases(),
        );
    }
}
