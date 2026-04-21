<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\View\View;

class DashboardHomeController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', [
            'usersCount' => User::query()->count(),
            'activeUsersCount' => User::query()->where('is_active', true)->count(),
            'verifiedUsersCount' => User::query()->where('is_verified', true)->count(),
            'busesCount' => Bus::query()->count(),
            'otpCount' => OtpCode::query()->count(),
        ]);
    }
}
