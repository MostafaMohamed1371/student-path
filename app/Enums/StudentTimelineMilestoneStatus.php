<?php

namespace App\Enums;

enum StudentTimelineMilestoneStatus: string
{
    case Scheduled = 'scheduled';
    case OnWay = 'on_way';
    case Arrived = 'arrived';
    case Boarded = 'boarded';
    case Completed = 'completed';
    case Absent = 'absent';

    public function labelEn(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::OnWay => 'On the way',
            self::Arrived => 'Arrived',
            self::Boarded => 'Boarded',
            self::Completed => 'Arrived',
            self::Absent => 'Absent',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدول',
            self::OnWay => 'في الطريق',
            self::Arrived => 'وصل السائق',
            self::Boarded => 'تم الركوب',
            self::Completed => 'تم الوصول',
            self::Absent => 'غائب',
        };
    }

    public function colorHex(): string
    {
        return match ($this) {
            self::Scheduled => '#78909C',
            self::OnWay, self::Arrived => '#0288D1',
            self::Boarded, self::Completed => '#00796B',
            self::Absent => '#D32F2F',
        };
    }

    public function backgroundColorHex(): string
    {
        return match ($this) {
            self::Scheduled => '#ECEFF1',
            self::OnWay, self::Arrived => '#E1F5FE',
            self::Boarded, self::Completed => '#E0F2F1',
            self::Absent => '#FFEBEE',
        };
    }
}
