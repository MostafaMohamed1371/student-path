<?php

namespace App\Enums;

enum PhoneAccountType: string
{
    case School = 'school';
    case Driver = 'driver';
    case Guardian = 'guardian';
    case Student = 'student';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::School => __('dashboard.phone_owner_school'),
            self::Driver => __('dashboard.phone_owner_driver'),
            self::Guardian => __('dashboard.phone_owner_guardian'),
            self::Student => __('dashboard.phone_owner_student'),
            self::Admin => __('dashboard.phone_owner_admin'),
        };
    }
}
