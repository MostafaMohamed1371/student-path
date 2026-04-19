<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DashboardLoginRequest;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DashboardLoginController extends Controller
{
    public function show(): View
    {
        return view('dashboard.login');
    }

    public function authenticate(DashboardLoginRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $national = $request->validated('phone');
        $phone = $phoneNormalizer->normalize($national);

        $user = User::query()
            ->where('phone', $phone)
            ->where('is_active', true)
            ->first();

        if (! $user || ! $user->password || ! Hash::check($request->validated('password'), $user->password)) {
            return back()
                ->withErrors(['phone' => __('dashboard.invalid_credentials')])
                ->onlyInput('phone');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
