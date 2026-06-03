<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DashboardLoginRequest;
use App\Http\Requests\Web\DashboardPhoneRequest;
use App\Http\Requests\Web\DashboardVerifyOtpRequest;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class DashboardLoginController extends Controller
{
    public function show(): View
    {
        return view('dashboard.login');
    }

    public function lookupPhone(DashboardPhoneRequest $request, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        $phone = $phoneNormalizer->normalize($request->validated('phone'));

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            return response()->json([
                'message' => __('dashboard.phone_not_registered'),
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => __('dashboard.account_disabled'),
            ], 422);
        }

        return response()->json([
            'login_mode' => $user->is_admin ? 'password' : 'otp',
        ]);
    }

    public function sendOtp(DashboardPhoneRequest $request, OtpService $otpService): JsonResponse
    {
        try {
            $otpService->sendForDashboard($request->validated('phone'));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (TooManyRequestsHttpException $e) {
            $retryAfter = (int) ($e->getHeaders()['Retry-After'][0] ?? 30);

            return response()->json([
                'message' => __('dashboard.otp_resend_wait', ['seconds' => $retryAfter]),
                'retry_after' => $retryAfter,
            ], 429);
        }

        return response()->json([
            'message' => __('dashboard.otp_sent'),
        ]);
    }

    public function verifyOtp(
        DashboardVerifyOtpRequest $request,
        OtpService $otpService,
    ): RedirectResponse {
        try {
            $user = $otpService->verifyForDashboard(
                $request->validated('phone'),
                $request->validated('code'),
            );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput($request->only('phone'))
                ->with('login_mode', 'otp');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function authenticate(DashboardLoginRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $national = $request->validated('phone');
        $phone = $phoneNormalizer->normalize($national);

        $user = User::query()
            ->where('phone', $phone)
            ->where('is_active', true)
            ->first();

        if (! $user || ! $user->is_admin) {
            return back()
                ->withErrors(['phone' => __('dashboard.invalid_credentials')])
                ->onlyInput('phone')
                ->with('login_mode', $user && ! $user->is_admin ? 'otp' : null);
        }

        if (! $user->password || ! Hash::check($request->validated('password'), $user->password)) {
            return back()
                ->withErrors(['phone' => __('dashboard.invalid_credentials')])
                ->onlyInput('phone')
                ->with('login_mode', 'password');
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
