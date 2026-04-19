<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    private const string PHONE_INPUT = '7701234567';

    private const string PHONE_CANONICAL = '9647701234567';

    public function test_send_otp_success(): void
    {
        Config::set('app.debug', false);

        $response = $this->postJson('/api/auth/send-otp', [
            'phone' => self::PHONE_INPUT,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OTP sent successfully')
            ->assertJsonMissingPath('data');

        $this->assertDatabaseHas('otp_codes', [
            'phone' => self::PHONE_CANONICAL,
        ]);

        $otp = OtpCode::query()->where('phone', self::PHONE_CANONICAL)->first();
        $this->assertNotNull($otp);
        $this->assertTrue(Hash::isHashed($otp->code));
    }

    public function test_send_otp_rejects_national_number_starting_with_zero(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'phone' => '0701234567',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['phone']]);
    }

    public function test_resend_blocked_before_cooldown(): void
    {
        Config::set('app.debug', false);

        $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT])->assertOk();

        $second = $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT]);

        $second->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['phone']]);
    }

    public function test_verify_otp_success(): void
    {
        $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT])->assertOk();
        $this->replaceLatestOtpHashWithPlainCode('1111');

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => self::PHONE_INPUT,
            'code' => '1111',
        ]);

        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'phone' => self::PHONE_CANONICAL,
        ]);

        $this->assertNotNull(User::query()->where('phone', self::PHONE_CANONICAL)->value('phone_verified_at'));
    }

    public function test_verify_otp_with_wrong_code(): void
    {
        $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT])->assertOk();
        $this->replaceLatestOtpHashWithPlainCode('2222');

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => self::PHONE_INPUT,
            'code' => '0000',
        ]);

        $verify->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['code']]);
    }

    public function test_verify_otp_after_expiry(): void
    {
        $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT])->assertOk();
        $this->replaceLatestOtpHashWithPlainCode('3333');

        $this->travel(6)->minutes();

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => self::PHONE_INPUT,
            'code' => '3333',
        ]);

        $verify->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_logout_success(): void
    {
        $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT])->assertOk();
        $this->replaceLatestOtpHashWithPlainCode('4444');

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => self::PHONE_INPUT,
            'code' => '4444',
        ]);

        $token = $verify->json('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $logout = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $logout->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // PHPUnit reuses the app between HTTP calls; RequestGuard caches the user on the
        // sanctum guard. Reset guards so the next request reflects the revoked token.
        Auth::forgetGuards();

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(401);
    }

    public function test_me_endpoint_success(): void
    {
        $this->postJson('/api/auth/send-otp', ['phone' => self::PHONE_INPUT])->assertOk();
        $this->replaceLatestOtpHashWithPlainCode('5555');

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => self::PHONE_INPUT,
            'code' => '5555',
        ]);

        $token = $verify->json('data.token');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', self::PHONE_CANONICAL);
    }

    /**
     * The send-otp endpoint does not return the OTP; tests set a known hash on the latest row.
     */
    private function replaceLatestOtpHashWithPlainCode(string $plainFourDigits): void
    {
        $otp = OtpCode::query()->where('phone', self::PHONE_CANONICAL)->latest('id')->first();
        $this->assertNotNull($otp);
        $otp->forceFill(['code' => Hash::make($plainFourDigits)])->save();
    }
}
