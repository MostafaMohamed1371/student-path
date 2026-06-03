<?php

namespace Tests\Feature;

use App\Enums\PhoneAccountType;
use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use App\Support\DashboardUserDisplayType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPhoneUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_use_phone_already_assigned_to_driver(): void
    {
        $school = $this->createSchool();
        $phone = '7900111222';

        $admin = User::factory()->create([
            'phone' => '9647900000001',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.drivers.store'), $this->driverPayload($school->id, $phone))
            ->assertRedirect(route('dashboard.drivers.index'));

        $guardianId = $this->createGuardianForSchool($school->id);

        $response = $this->post(route('dashboard.students.store'), [
            'school_id' => $school->id,
            'full_name' => 'Ali Hassan Mahmoud',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => $phone,
            'guardian_id' => $guardianId,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'Landmark',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('student_phone');
    }

    public function test_school_update_allows_same_admin_phone_after_user_sync(): void
    {
        $admin = User::factory()->create([
            'phone' => '9647900000003',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $phone = '7900444555';

        $this->post(route('dashboard.schools.store'), [
            'name_ar' => 'مدرسة',
            'name_en' => 'School Update Phone',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street',
            'status' => 'active',
            'admin_phone' => $phone,
        ])->assertRedirect(route('dashboard.schools.index'));

        $school = School::query()->where('name_en', 'School Update Phone')->first();
        $this->assertNotNull($school);

        $schoolUser = User::query()->where('phone', '964'.$phone)->first();
        $this->assertNotNull($schoolUser);

        $this->put(route('dashboard.schools.update', $school), [
            'name_ar' => 'مدرسة محدثة',
            'name_en' => 'School Update Phone Revised',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street',
            'status' => 'active',
            'admin_phone' => $phone,
        ])->assertRedirect(route('dashboard.schools.index'));

        $this->assertSame('School Update Phone Revised', $school->fresh()->name_en);
    }

    public function test_school_synced_user_shows_school_type_in_display_helper(): void
    {
        $school = $this->createSchool();
        $user = User::factory()->create([
            'phone' => '9647900888777',
            'school_id' => $school->id,
            'phone_account_type' => PhoneAccountType::School->value,
            'is_admin' => false,
        ]);

        $this->assertSame('school', DashboardUserDisplayType::resolve($user));
    }

    public function test_school_admin_phone_creates_school_account_type_user(): void
    {
        $admin = User::factory()->create([
            'phone' => '9647900000002',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $phone = '7900333444';

        $this->post(route('dashboard.schools.store'), [
            'name_ar' => 'مدرسة',
            'name_en' => 'School Phone Test',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street',
            'status' => 'active',
            'admin_phone' => $phone,
        ])->assertRedirect(route('dashboard.schools.index'));

        $user = User::query()->where('phone', '964'.$phone)->first();
        $this->assertNotNull($user);
        $this->assertSame(PhoneAccountType::School->value, $user->phone_account_type);
    }

    private function createSchool(): School
    {
        return School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Test School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'St',
            'status' => 'active',
        ]);
    }

    private function createGuardianForSchool(int $schoolId): int
    {
        $response = $this->post(route('dashboard.guardians.store'), [
            'school_id' => $schoolId,
            'full_name' => 'Guardian One',
            'phone' => '7900555666',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('dashboard.guardians.index'));

        return (int) \App\Models\Guardian::query()->where('phone', '7900555666')->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(int $schoolId, string $phone): array
    {
        return [
            'school_id' => $schoolId,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'User',
            'age' => 30,
            'id_card_number' => 'ID'.substr($phone, -6),
            'license_number' => 'LIC'.substr($phone, -6),
            'primary_phone' => $phone,
            'emergency_phone' => '7900999888',
            'residential_address' => 'Address',
            'status' => 'active',
        ];
    }
}
