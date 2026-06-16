<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\OtpCode;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    private const string PHONE_INPUT = '7701234567';

    private const string PHONE_CANONICAL = '9647701234567';

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::query()->create([
            'name_ar' => 'OTP Base School',
            'name_en' => 'OTP Base School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'phone' => self::PHONE_CANONICAL,
            'is_active' => true,
        ]);
        Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'first_name' => 'Test',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'Driver',
            'age' => 30,
            'id_card_number' => 'ID123456',
            'license_number' => 'LIC123',
            'primary_phone' => self::PHONE_INPUT,
            'emergency_phone' => self::PHONE_INPUT,
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
    }

    /** @return array{phone: string, type_user: string} */
    private function sendOtpBody(string $phone = self::PHONE_INPUT, string $typeUser = 'driver'): array
    {
        return ['phone' => $phone, 'type_user' => $typeUser];
    }

    /** @return array{phone: string, code: string, type_user: string} */
    private function verifyOtpBody(string $code, string $phone = self::PHONE_INPUT, string $typeUser = 'driver'): array
    {
        return ['phone' => $phone, 'code' => $code, 'type_user' => $typeUser];
    }

    public function test_send_otp_success(): void
    {
        Config::set('app.debug', false);

        $response = $this->postJson('/api/auth/send-otp', $this->sendOtpBody());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OTP sent successfully')
            ->assertJsonStructure(['data' => ['otp_code']]);

        $this->assertDatabaseHas('otp_codes', [
            'phone' => self::PHONE_CANONICAL,
        ]);

        $otp = OtpCode::query()->where('phone', self::PHONE_CANONICAL)->first();
        $this->assertNotNull($otp);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $otp->code);
    }

    public function test_send_otp_rejects_national_number_starting_with_zero(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody('0701234567'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['phone']]);
    }

    public function test_send_otp_fails_for_unregistered_phone(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody('7711111111'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['phone']]);

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_send_otp_requires_type_user(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'phone' => self::PHONE_INPUT,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);
    }

    public function test_verify_otp_requires_type_user(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('8888');

        $this->postJson('/api/auth/verify-otp', [
            'phone' => self::PHONE_INPUT,
            'code' => '8888',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);
    }

    public function test_send_otp_rejects_invalid_type_user(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'phone' => self::PHONE_INPUT,
            'type_user' => 'admin',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);
    }

    public function test_send_otp_type_user_driver_rejects_user_without_driver_profile(): void
    {
        Config::set('app.debug', false);
        User::factory()->create([
            'phone' => '9647722222222',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/send-otp', $this->sendOtpBody('7722222222'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_send_otp_type_user_guardian_rejects_user_without_guardian_context(): void
    {
        Config::set('app.debug', false);

        $this->postJson('/api/auth/send-otp', [
            'phone' => self::PHONE_INPUT,
            'type_user' => 'guardian',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_send_otp_type_user_guardian_succeeds_when_user_has_guardian(): void
    {
        Config::set('app.debug', false);
        $school = School::query()->create([
            'name_ar' => 'OTP School',
            'name_en' => 'OTP School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'OTP Guardian',
            'phone' => self::PHONE_CANONICAL,
            'status' => 'active',
        ]);
        User::query()->where('phone', self::PHONE_CANONICAL)->update([
            'guardian_id' => $guardian->id,
        ]);

        $this->postJson('/api/auth/send-otp', [
            'phone' => self::PHONE_INPUT,
            'type_user' => 'guardian',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('otp_codes', [
            'phone' => self::PHONE_CANONICAL,
        ]);
    }

    public function test_send_otp_type_user_guardian_succeeds_when_guardian_phone_is_national_ten_digits(): void
    {
        Config::set('app.debug', false);
        $school = School::query()->create([
            'name_ar' => 'OTP School Nat',
            'name_en' => 'OTP School Nat',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian National Phone',
            'phone' => '7719999999',
            'status' => 'active',
        ]);
        User::factory()->create([
            'phone' => '9647719999999',
            'is_active' => true,
            'guardian_id' => null,
        ]);

        $this->postJson('/api/auth/send-otp', [
            'phone' => '7719999999',
            'type_user' => 'guardian',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('otp_codes', [
            'phone' => '9647719999999',
        ]);
    }

    public function test_send_otp_type_user_student_rejects_when_no_matching_student_phone(): void
    {
        Config::set('app.debug', false);

        $this->postJson('/api/auth/send-otp', [
            'phone' => self::PHONE_INPUT,
            'type_user' => 'student',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_send_otp_type_user_student_succeeds_when_student_phone_matches_user(): void
    {
        Config::set('app.debug', false);
        $school = School::query()->create([
            'name_ar' => 'OTP School 2',
            'name_en' => 'OTP School 2',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G2',
            'phone' => '9647300000001',
            'status' => 'active',
        ]);
        Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student OTP',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7709876543',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        User::factory()->create([
            'phone' => '9647709876543',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/send-otp', [
            'phone' => '7709876543',
            'type_user' => 'student',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_verify_otp_rejects_type_user_student_when_not_a_student_account(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('6666');

        $this->postJson('/api/auth/verify-otp', $this->verifyOtpBody('6666', self::PHONE_INPUT, 'student'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type_user']]);
    }

    public function test_resend_blocked_before_cooldown(): void
    {
        Config::set('app.debug', false);
        Config::set('otp.resend_seconds', 30);

        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();

        $second = $this->postJson('/api/auth/send-otp', $this->sendOtpBody());

        $second->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['phone']]);
    }

    public function test_verify_otp_success(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('1111');

        $verify = $this->postJson('/api/auth/verify-otp', $this->verifyOtpBody('1111'));

        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.type_user', 'driver')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'phone' => self::PHONE_CANONICAL,
        ]);

        $this->assertNotNull(User::query()->where('phone', self::PHONE_CANONICAL)->value('phone_verified_at'));
    }

    public function test_verify_otp_with_wrong_code(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('2222');

        $verify = $this->postJson('/api/auth/verify-otp', $this->verifyOtpBody('0000'));

        $verify->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['code']]);
    }

    public function test_verify_otp_after_expiry(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('3333');

        $this->travel(6)->minutes();

        $verify = $this->postJson('/api/auth/verify-otp', $this->verifyOtpBody('3333'));

        $verify->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_logout_success(): void
    {
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('4444');

        $verify = $this->postJson('/api/auth/verify-otp', $this->verifyOtpBody('4444'));

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
        $this->postJson('/api/auth/send-otp', $this->sendOtpBody())->assertOk();
        $this->replaceLatestOtpCode('5555');

        $verify = $this->postJson('/api/auth/verify-otp', $this->verifyOtpBody('5555'));

        $token = $verify->json('data.token');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', self::PHONE_INPUT)
            ->assertJsonPath('data.user.type_user', 'driver');
    }

    /**
     * The send-otp endpoint does not return OTP; tests set a known code on latest row.
     */
    private function replaceLatestOtpCode(string $plainFourDigits): void
    {
        $otp = OtpCode::query()->where('phone', self::PHONE_CANONICAL)->latest('id')->first();
        $this->assertNotNull($otp);
        $otp->forceFill(['code' => $plainFourDigits])->save();
    }
}
