<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\OtpCode;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\View\View;

class DashboardHomeController extends Controller
{
    public function index(): View
    {
        $authUser = auth()->user();
        $isAdmin = (bool) $authUser?->is_admin;
        $schoolId = $authUser?->scopingSchoolId();

        if ($isAdmin) {
            return view('dashboard.index', [
                'schoolsCount' => School::query()->count(),
                'driversCount' => Driver::query()->count(),
                'studentsCount' => Student::query()->count(),
                'guardiansCount' => Guardian::query()->count(),
                'usersCount' => User::query()->count(),
                'activeUsersCount' => User::query()->where('is_active', true)->count(),
                'verifiedUsersCount' => User::query()->where('is_verified', true)->count(),
                'busesCount' => Bus::query()->count(),
                'assignedBusesCount' => Bus::query()->whereNotNull('driver_id')->count(),
                'otpCount' => OtpCode::query()->count(),
            ]);
        }

        $driverIds = $schoolId === null
            ? collect()
            : Driver::query()->where('school_id', $schoolId)->pluck('id');

        return view('dashboard.index', [
            'schoolsCount' => $schoolId ? 1 : 0,
            'driversCount' => Driver::query()->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->count(),
            'studentsCount' => Student::query()->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->count(),
            'guardiansCount' => Guardian::query()->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->count(),
            'usersCount' => User::query()->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->count(),
            'activeUsersCount' => User::query()
                ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
                ->where('is_active', true)
                ->count(),
            'verifiedUsersCount' => User::query()
                ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
                ->where('is_verified', true)
                ->count(),
            'busesCount' => Bus::query()->whereIn('driver_id', $driverIds)->count(),
            'assignedBusesCount' => Bus::query()->whereIn('driver_id', $driverIds)->whereNotNull('driver_id')->count(),
            'otpCount' => OtpCode::query()->where('phone', $authUser?->phone)->count(),
        ]);
    }
}
