<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSchoolScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_sees_only_their_school_list(): void
    {
        $schoolA = $this->makeSchool('School A');
        $schoolB = $this->makeSchool('School B');
        $user = User::factory()->create([
            'phone' => '9647901111111',
            'school_id' => $schoolA->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/org/schools')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/org/schools')
            ->assertJsonPath('data.0.schoolId', (string) $schoolA->id);
    }

    public function test_non_admin_cannot_create_school(): void
    {
        $schoolA = $this->makeSchool('School A');
        $user = User::factory()->create([
            'phone' => '9647902222222',
            'school_id' => $schoolA->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/org/schools', [
            'schoolNameAr' => 'جديد',
            'schoolNameEn' => 'New',
            'province' => 'B',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ])->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_cannot_view_driver_from_another_school(): void
    {
        $schoolA = $this->makeSchool('School A');
        $schoolB = $this->makeSchool('School B');
        $uB = User::factory()->create(['phone' => '9647903333333']);
        $driverB = Driver::query()->create([
            'user_id' => $uB->id,
            'school_id' => $schoolB->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'D',
            'age' => 30,
            'id_card_number' => '1',
            'license_number' => '1',
            'primary_phone' => '7770111111',
            'emergency_phone' => '7770222222',
            'residential_address' => 'R',
            'status' => 'active',
        ]);

        $staffA = User::factory()->create([
            'phone' => '9647904444444',
            'school_id' => $schoolA->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($staffA);

        $this->getJson('/api/org/drivers/'.$driverB->id)
            ->assertStatus(403);
    }

    public function test_admin_may_list_all_schools(): void
    {
        $this->makeSchool('One');
        $this->makeSchool('Two');
        $admin = User::factory()->create([
            'phone' => '9647905555555',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/org/schools')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_admin_cannot_update_school_via_api(): void
    {
        $school = $this->makeSchool('To Update');
        $staff = User::factory()->create([
            'phone' => '9647906666666',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($staff);

        $this->putJson("/api/org/schools/{$school->id}", ['status' => 'inactive'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_cannot_mutate_students_or_guardians_via_api(): void
    {
        $school = $this->makeSchool('Scoped S');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id, 'full_name' => 'Parent P', 'phone' => '7300000000', 'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id, 'guardian_id' => $guardian->id, 'full_name' => 'Student S', 'gender' => 'male',
            'grade' => '1', 'student_phone' => '7400000000', 'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone, 'relationship' => 'father',
            'district_area' => 'D', 'nearest_landmark' => 'L', 'status' => 'active',
        ]);

        $staff = User::factory()->create([
            'phone' => '9647908000000',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/org/guardians', [
            'schoolId' => $school->id, 'fullName' => 'New G', 'phone' => '7500000000', 'status' => 'active',
        ])->assertStatus(403);
        $this->putJson("/api/org/guardians/{$guardian->id}", ['fullName' => 'Changed'])->assertStatus(403);
        $this->deleteJson("/api/org/guardians/{$guardian->id}")->assertStatus(403);

        $this->postJson('/api/org/students', [
            'schoolId' => $school->id,
            'guardianId' => $guardian->id,
            'fullName' => 'Child C',
            'gender' => 'male',
            'grade' => '1',
            'studentPhone' => '7600000000',
            'relationship' => 'father',
            'districtArea' => 'D2',
            'nearestLandmark' => 'L2',
            'status' => 'active',
        ])->assertStatus(403);
        $this->putJson("/api/org/students/{$student->id}", ['grade' => '2'])->assertStatus(403);
        $this->deleteJson("/api/org/students/{$student->id}")->assertStatus(403);
    }

    public function test_non_admin_cannot_mutate_drivers_resource_via_api(): void
    {
        $school = $this->makeSchool('D School');
        $u = User::factory()->create(['phone' => '9647909000000']);
        $driver = Driver::query()->create([
            'user_id' => $u->id,
            'school_id' => $school->id,
            'first_name' => 'A', 'father_name' => 'A', 'grandfather_name' => 'A', 'last_name' => 'A',
            'age' => 35, 'id_card_number' => 'DID1', 'license_number' => 'LIC1',
            'primary_phone' => '7700000000', 'emergency_phone' => '7700000001',
            'residential_address' => 'R', 'status' => 'active',
        ]);
        $staff = User::factory()->create([
            'phone' => '9647909100000',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/org/drivers', [
            'schoolId' => $school->id,
            'firstName' => 'N', 'fatherName' => 'N', 'grandfatherName' => 'N', 'lastName' => 'N',
            'age' => 30, 'idCardNumber' => 'NEW1', 'licenseNumber' => 'LNEW',
            'primaryPhone' => '7800000000', 'emergencyPhone' => '7800000001',
            'residentialAddress' => 'A', 'status' => 'active',
        ])->assertStatus(403);
        $this->putJson("/api/org/drivers/{$driver->id}", ['firstName' => 'B'])->assertStatus(403);
        $this->deleteJson("/api/org/drivers/{$driver->id}")->assertStatus(403);
    }

    private function makeSchool(string $nameEn): School
    {
        return School::query()->create([
            'name_ar' => $nameEn,
            'name_en' => $nameEn,
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
    }
}
