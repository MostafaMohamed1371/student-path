<?php

namespace App\Http\Controllers\Api;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends Controller
{
    public function sendOtp(SendOtpRequest $request, OtpService $otpService): JsonResponse
    {
        try {
            $otpService->send($request->validated('phone'), OtpPurpose::Login);
        } catch (TooManyRequestsHttpException $e) {
            $retryAfter = (int) ($e->getHeaders()['Retry-After'][0] ?? 30);

            return ApiResponse::error(
                'Please wait before requesting another code.',
                [
                    'phone' => ["Try again in {$retryAfter} seconds."],
                ],
                429
            );
        }

        // Intentionally no "data" payload: timing hints and OTP must not appear in the API (SMS only).
        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();
        $payload = $otpService->verify($validated['phone'], $validated['code'], OtpPurpose::Login);

        return ApiResponse::success('Authenticated successfully', [
            'token' => $payload['token'],
            'token_type' => 'Bearer',
            'user' => (new UserResource($payload['user']))->toArray($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Prefer resolving the DB row from the bearer secret so revocation always hits
        // personal_access_tokens even if currentAccessToken() is not hydrated.
        $bearer = (string) $request->bearerToken();
        if ($bearer !== '') {
            $accessToken = PersonalAccessToken::findToken($bearer);
            $accessToken?->delete();
        }

        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully', null);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('Profile retrieved successfully', [
            'user' => (new UserResource($request->user()))->toArray($request),
        ]);
    }
}
