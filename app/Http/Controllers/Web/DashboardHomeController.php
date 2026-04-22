<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\OtpCode;
use App\Models\School;
use App\Models\User;
use Illuminate\View\View;

class DashboardHomeController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', [
            'schoolsCount' => School::query()->count(),
            'driversCount' => Driver::query()->count(),
            'usersCount' => User::query()->count(),
            'activeUsersCount' => User::query()->where('is_active', true)->count(),
            'verifiedUsersCount' => User::query()->where('is_verified', true)->count(),
            'busesCount' => Bus::query()->count(),
            'assignedBusesCount' => Bus::query()->whereNotNull('driver_id')->count(),
            'otpCount' => OtpCode::query()->count(),
        ]);
    }
}
