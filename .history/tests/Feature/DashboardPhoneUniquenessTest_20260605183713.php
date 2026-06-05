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
            'home_address' => 'Home address',
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

    public function test_guardian_update_allows_unchanged_phone_when_editing_other_fields(): void
    {
        $school = $this->createSchool();
        $phone = '7900666111';

        $admin = User::factory()->create([
            'phone' => '9647900000004',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $school->id,
            'full_name' => 'Guardian Original',
            'phone' => $phone,
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $guardian = \App\Models\Guardian::query()->where('phone', $phone)->firstOrFail();
        $this->assertNotNull(User::query()->where('phone', '964'.$phone)->first());

        $this->put(route('dashboard.guardians.update', $guardian), [
            'school_id' => $school->id,
            'full_name' => 'Guardian Updated Name',
            'phone' => $phone,
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $this->assertSame('Guardian Updated Name', $guardian->fresh()->full_name);
    }

    public function test_student_update_allows_unchanged_phone_when_editing_other_fields(): void
    {
        $school = $this->createSchool();
        $phone = '7900666222';

        $admin = User::factory()->create([
            'phone' => '9647900000005',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $guardianId = $this->createGuardianForSchool($school->id);

        $this->post(route('dashboard.students.store'), [
            'school_id' => $school->id,
            'full_name' => 'Ali Hassan Mahmoud',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => $phone,
            'guardian_id' => $guardianId,
            'relationship' => 'father',
            'home_address' => 'Home address',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.students.index'));

        $student = \App\Models\Student::query()->where('student_phone', $phone)->firstOrFail();
        $this->assertNotNull(User::query()->where('phone', '964'.$phone)->first());

        $this->put(route('dashboard.students.update', $student), [
            'school_id' => $school->id,
            'full_name' => 'Ali Hassan Mahmoud',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => $phone,
            'guardian_id' => $guardianId,
            'relationship' => 'father',
            'home_address' => 'New home address',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.students.index'));

        $student->refresh();
        $this->assertSame('2', $student->grade);
        $this->assertSame('New home address', $student->nearest_landmark);
    }

    public function test_driver_update_allows_unchanged_phone_when_editing_other_fields(): void
    {
        $school = $this->createSchool();
        $phone = '7900666333';

        $admin = User::factory()->create([
            'phone' => '9647900000006',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.drivers.store'), $this->driverPayload($school->id, $phone))
            ->assertRedirect(route('dashboard.drivers.index'));

        $driver = Driver::query()->where('primary_phone', $phone)->firstOrFail();
        $this->assertNotNull(User::query()->where('phone', '964'.$phone)->first());

        $payload = $this->driverPayload($school->id, $phone);
        $payload['residential_address'] = 'Updated address';

        $this->put(route('dashboard.drivers.update', $driver), $payload)
            ->assertRedirect(route('dashboard.drivers.index'));

        $this->assertSame('Updated address', $driver->fresh()->residential_address);
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
