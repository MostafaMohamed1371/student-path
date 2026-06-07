<?php

namespace App\Enums;

enum StudentTimelineMilestoneCode: string
{
    case MorningPickupHome = 'morning_pickup_home';
    case MorningArriveSchool = 'morning_arrive_school';
    case EveningPickupSchool = 'evening_pickup_school';
    case EveningArriveHome = 'evening_arrive_home';

    public function titleEn(): string
    {
        return match ($this) {
            self::MorningPickupHome => 'Departure time',
            self::MorningArriveSchool => 'Arrival at school',
            self::EveningPickupSchool => 'Return time',
            self::EveningArriveHome => 'Arrival home',
        };
    }

    public function titleAr(): string
    {
        return match ($this) {
            self::MorningPickupHome => 'موعد الذهاب',
            self::MorningArriveSchool => 'الوصول للمدرسة',
            self::EveningPickupSchool => 'موعد العودة',
            self::EveningArriveHome => 'الوصول للمنزل',
        };
    }

    public function descriptionEn(): string
    {
        return match ($this) {
            self::MorningPickupHome => 'Pickup from home location',
            self::MorningArriveSchool => 'Main school gate',
            self::EveningPickupSchool => 'Pickup from school',
            self::EveningArriveHome => 'Return home',
        };
    }

    public function descriptionAr(): string
    {
        return match ($this) {
            self::MorningPickupHome => 'الاستلام من موقع المنزل',
            self::MorningArriveSchool => 'بوابة المدرسة الرئيسية',
            self::EveningPickupSchool => 'الاستلام من المدرسة',
            self::EveningArriveHome => 'العودة للمنزل',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MorningPickupHome, self::EveningArriveHome => 'home',
            self::MorningArriveSchool => 'school',
            self::EveningPickupSchool => 'bus',
        };
    }
}
