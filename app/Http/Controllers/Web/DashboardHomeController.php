<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
            'otpCount' => OtpCode::query()->count(),
        ]);
    }
}
