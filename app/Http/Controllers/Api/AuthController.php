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
            $validated = $request->validated();
            $result = $otpService->send(
                $validated['phone'],
                OtpPurpose::Login,
                $validated['type_user'],
            );
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

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'otp_code' => $result['plain_code'],
            ],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();
        $payload = $otpService->verify(
            $validated['phone'],
            $validated['code'],
            OtpPurpose::Login,
            $validated['type_user'],
        );
        $user = $payload['user']->load(['driver', 'school', 'guardian']);

        return ApiResponse::success('Authenticated successfully', [
            'token' => $payload['token'],
            'token_type' => 'Bearer',
            'user' => (new UserResource($user, $validated['type_user']))->toArray($request),
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
        $user = $request->user()->load(['driver', 'school', 'guardian']);

        return ApiResponse::success('Profile retrieved successfully', [
            'user' => (new UserResource($user))->toArray($request),
        ]);
    }
}
