<?php

namespace App\Services\Otp;

use App\Contracts\Sms\SmsSender;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use App\Support\LoginTypeUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class OtpService
{
    private const int OTP_LENGTH = 4;

    private const int EXPIRE_MINUTES = 5;

    private const int MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SmsSender $smsSender,
    ) {}

    /**
     * Dashboard login for non-admin users (session auth, no Sanctum token).
     *
     * @return array{expires_in: int, resend_in: int, plain_code: string}
     */
    public function sendForDashboard(string $rawPhone): array
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is not registered.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['This account is disabled.'],
            ]);
        }

        if ($user->is_admin) {
            throw ValidationException::withMessages([
                'phone' => ['Administrators must sign in with a password.'],
            ]);
        }

        return $this->send($rawPhone, OtpPurpose::Login, 'dashboard');
    }

    public function verifyForDashboard(string $rawPhone, string $rawCode): User
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        $code = preg_replace('/\D+/', '', $rawCode) ?? '';

        $staticPlain = $this->staticOtpPlain();
        if ($staticPlain !== null && hash_equals($staticPlain, $code)) {
            return $this->verifyDashboardWithStaticOtp($phone);
        }

        return DB::transaction(function () use ($phone, $code): User {
            /** @var OtpCode|null $otp */
            $otp = OtpCode::query()
                ->forPhoneAndPurpose($phone, OtpPurpose::Login)
                ->active()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $otp) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid or expired verification code.'],
                ]);
            }

            if ($otp->attempts >= $otp->max_attempts) {
                throw ValidationException::withMessages([
                    'code' => ['Too many failed attempts. Please request a new code.'],
                ]);
            }

            if (! hash_equals((string) $otp->code, (string) $code)) {
                $otp->increment('attempts');

                throw ValidationException::withMessages([
                    'code' => ['Invalid verification code.'],
                ]);
            }

            $otp->forceFill(['verified_at' => now()])->save();

            return $this->resolveDashboardUserAfterOtp($phone);
        });
    }

    /**
     * @return array{expires_in: int, resend_in: int, plain_code: string}
     */
    public function send(string $rawPhone, OtpPurpose $purpose, string $typeUser): array
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        $user = User::query()->where('phone', $phone)->first();

        // Login-only OTP: do not send a code for unknown phones.
        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is not registered.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['This account is disabled.'],
            ]);
        }

        if ($typeUser !== 'dashboard') {
            LoginTypeUser::assertMatches($typeUser, $user);
        } elseif ($user->is_admin) {
            throw ValidationException::withMessages([
                'phone' => ['Administrators must sign in with a password.'],
            ]);
        }

        $latest = OtpCode::query()
            ->forPhoneAndPurpose($phone, $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        $resendCooldown = $this->sendResendCooldownSeconds();

        // Resend cooldown (optional): only when otp.resend_seconds > 0.
        if ($resendCooldown > 0 && $latest && $latest->resend_available_at->isFuture()) {
            $retryAfter = max(1, (int) now()->diffInSeconds($latest->resend_available_at, false));

            throw new TooManyRequestsHttpException(
                $retryAfter,
                'Please wait before requesting another code.',
            );
        }

        // 4-digit numeric OTP stored in plain text by request (visible in DB).
        $plain = $this->staticOtpPlain() ?? str_pad((string) random_int(0, 9999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($phone, $purpose, $plain, $resendCooldown): void {
            // Invalidate prior outstanding OTPs so only the newest row can ever verify.
            OtpCode::query()
                ->forPhoneAndPurpose($phone, $purpose)
                ->whereNull('verified_at')
                ->update(['expires_at' => now()]);
            // Security: mark old rows expired immediately; prevents parallel valid codes.

            $resendAt = $resendCooldown > 0
                ? now()->addSeconds($resendCooldown)
                : now();

            OtpCode::create([
                'phone' => $phone,
                'code' => $plain,
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes(self::EXPIRE_MINUTES),
                'resend_available_at' => $resendAt,
                'verified_at' => null,
                'attempts' => 0,
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);
        });

        $message = sprintf('Your verification code is: %s', $plain);

        try {
            $this->smsSender->send($phone, $message, [
                'purpose' => $purpose->value,
                // Arabic SMS copy should be composed here or in the real SmsSender implementation.
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::error('OTP SMS gateway failed after code was stored; client may still verify using the issued code.', [
                'phone' => $phone,
                'purpose' => $purpose->value,
            ]);
        }

        return [
            'expires_in' => self::EXPIRE_MINUTES * 60,
            'resend_in' => $resendCooldown,
            'plain_code' => $plain,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    public function verify(string $rawPhone, string $rawCode, OtpPurpose $purpose, string $typeUser): array
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        $code = preg_replace('/\D+/', '', $rawCode) ?? '';

        $staticPlain = $this->staticOtpPlain();
        if ($purpose === OtpPurpose::Login && $staticPlain !== null && hash_equals($staticPlain, $code)) {
            return $this->verifyWithStaticOtp($phone, $typeUser);
        }

        return DB::transaction(function () use ($phone, $code, $purpose, $typeUser): array {
            /** @var OtpCode|null $otp */
            $otp = OtpCode::query()
                ->forPhoneAndPurpose($phone, $purpose)
                ->active()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $otp) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid or expired verification code.'],
                ]);
            }

            if ($otp->attempts >= $otp->max_attempts) {
                throw ValidationException::withMessages([
                    'code' => ['Too many failed attempts. Please request a new code.'],
                ]);
            }

            if (! hash_equals((string) $otp->code, (string) $code)) {
                $otp->increment('attempts');

                throw ValidationException::withMessages([
                    'code' => ['Invalid verification code.'],
                ]);
            }

            // One-time use: mark verified so this OTP can never authenticate again.
            $otp->forceFill(['verified_at' => now()])->save();

            $user = User::query()->where('phone', $phone)->lockForUpdate()->first();
            if (! $user) {
                throw ValidationException::withMessages([
                    'phone' => ['This phone number is not registered.'],
                ]);
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'phone' => ['This account is disabled.'],
                ]);
            }

            LoginTypeUser::assertMatches($typeUser, $user);

            if ($user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            $token = $user->createToken('mobile')->plainTextToken;

            return [
                'user' => $user->fresh(),
                'token' => $token,
            ];
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    private function verifyDashboardWithStaticOtp(string $phone): User
    {
        return DB::transaction(fn (): User => $this->resolveDashboardUserAfterOtp($phone, lock: true));
    }

    private function resolveDashboardUserAfterOtp(string $phone, bool $lock = false): User
    {
        $query = User::query()->where('phone', $phone);
        if ($lock) {
            $query->lockForUpdate();
        }
        $user = $query->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is not registered.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['This account is disabled.'],
            ]);
        }

        if ($user->is_admin) {
            throw ValidationException::withMessages([
                'phone' => ['Administrators must sign in with a password.'],
            ]);
        }

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $user->fresh();
    }

    private function verifyWithStaticOtp(string $phone, string $typeUser): array
    {
        return DB::transaction(function () use ($phone, $typeUser): array {
            $user = User::query()->where('phone', $phone)->lockForUpdate()->first();
            if (! $user) {
                throw ValidationException::withMessages([
                    'phone' => ['This phone number is not registered.'],
                ]);
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'phone' => ['This account is disabled.'],
                ]);
            }

            if ($typeUser !== 'dashboard') {
                LoginTypeUser::assertMatches($typeUser, $user);
            } elseif ($user->is_admin) {
                throw ValidationException::withMessages([
                    'phone' => ['Administrators must sign in with a password.'],
                ]);
            }

            if ($user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            $token = $user->createToken('mobile')->plainTextToken;

            return [
                'user' => $user->fresh(),
                'token' => $token,
            ];
        });
    }

    private function sendResendCooldownSeconds(): int
    {
        return max(0, (int) config('otp.resend_seconds', 0));
    }

    /**
     * Normalized 4-digit code when OTP_STATIC_CODE is set (any APP_ENV).
     */
    private function staticOtpPlain(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) config('otp.static_code', '')) ?? '';
        if ($digits === '') {
            return null;
        }

        $trimmed = substr($digits, -self::OTP_LENGTH);

        return str_pad($trimmed, self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }
}
