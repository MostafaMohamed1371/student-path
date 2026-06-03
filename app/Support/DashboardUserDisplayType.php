<?php

namespace App\Support;

use App\Models\User;
use App\Services\Phone\DashboardPhoneRegistry;

/**
 * Account type label for dashboard user reports (school, driver, guardian, student, admin).
 */
final class DashboardUserDisplayType
{
    public static function resolve(User $user): ?string
    {
        return app(DashboardPhoneRegistry::class)->accountTypeForUser($user)->value;
    }
}
