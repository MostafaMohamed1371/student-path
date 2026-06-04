<?php

namespace Tests\Feature;

use App\Enums\PhoneAccountType;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIdCardUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_cannot_reuse_driver_id_card_number(): void
    {
        $school = $this->createSchool();
        $idCard = 'ABC123456';

        $admin = User::factory()->create([
            'phone' => '9647900000001',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.drivers.store'), $this->driverPayload($school->id, '7900111222', $idCard))
            ->assertRedirect(route('dashboard.drivers.index'));

        $response = $this->post(route('dashboard.guardians.store'), [
            'school_id' => $school->id,
            'full_name' => 'Guardian Two',
            'phone' => '7900555666',
            'id_card_number' => $idCard,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('id_card_number');
    }

    public function test_same_guardian_id_card_allowed_at_different_schools(): void
    {
        $schoolA = $this->createSchool('School A');
        $schoolB = $this->createSchool('School B');
        $idCard = 'SHARED-ID-001';

        $admin = User::factory()->create([
            'phone' => '9647900000010',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $schoolA->id,
            'full_name' => 'Cross School Guardian',
            'phone' => '7900111001',
            'id_card_number' => $idCard,
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $schoolB->id,
            'full_name' => 'Cross School Guardian',
            'phone' => '7900111001',
            'id_card_number' => $idCard,
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $this->assertSame(2, Guardian::query()->where('id_card_number', $idCard)->count());
    }

    public function test_student_lookup_provisions_guardian_for_another_school(): void
    {
        $schoolA = $this->createSchool('School A');
        $schoolB = $this->createSchool('School B');

        $admin = User::factory()->create([
            'phone' => '9647900000011',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $schoolA->id,
            'full_name' => 'Provision Guardian',
            'phone' => '7900222002',
            'id_card_number' => 'PROVISION-1',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $response = $this->getJson(route('dashboard.students.lookup_guardian', [
            'school_id' => $schoolB->id,
            'id_card_number' => 'provision-1',
            'ensure_for_school' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('provisioned_for_school', true)
            ->assertJsonPath('guardian.school_matches', true)
            ->assertJsonPath('guardian.full_name', 'Provision Guardian');

        $atSchoolB = Guardian::query()
            ->where('school_id', $schoolB->id)
            ->where('id_card_number', 'PROVISION-1')
            ->first();

        $this->assertNotNull($atSchoolB);
        $this->assertSame('7900222002', $atSchoolB->phone);
    }

    public function test_guardian_create_form_lookup_fills_profile(): void
    {
        $schoolA = $this->createSchool('School A');
        $schoolB = $this->createSchool('School B');

        $admin = User::factory()->create([
            'phone' => '9647900000012',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $schoolA->id,
            'full_name' => 'Autofill Source',
            'phone' => '7900333003',
            'backup_phone' => '7900333004',
            'id_card_number' => 'AUTOFILL-1',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $response = $this->getJson(route('dashboard.guardians.lookup_by_id_card', [
            'school_id' => $schoolB->id,
            'id_card_number' => 'autofill-1',
        ]));

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('already_at_school', false)
            ->assertJsonPath('guardian.full_name', 'Autofill Source')
            ->assertJsonPath('guardian.phone', '7900333003')
            ->assertJsonPath('guardian.backup_phone', '7900333004')
            ->assertJsonPath('guardian.id_card_number', 'AUTOFILL-1');
    }

    public function test_student_lookup_guardian_by_id_card(): void
    {
        $school = $this->createSchool();

        $admin = User::factory()->create([
            'phone' => '9647900000002',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $school->id,
            'full_name' => 'Guardian Lookup',
            'phone' => '7900666777',
            'id_card_number' => 'guard-lookup-1',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $guardian = Guardian::query()->where('phone', '7900666777')->first();
        $this->assertNotNull($guardian);
        $this->assertSame('GUARD-LOOKUP-1', $guardian->id_card_number);

        $response = $this->getJson(route('dashboard.students.lookup_guardian', [
            'school_id' => $school->id,
            'id_card_number' => 'guard-lookup-1',
        ]));

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('guardian.id', $guardian->id)
            ->assertJsonPath('guardian.school_matches', true);
    }

    public function test_student_create_does_not_convert_guardian_user_to_student(): void
    {
        $school = $this->createSchool();
        $sharedPhone = '7900888999';

        $admin = User::factory()->create([
            'phone' => '9647900000003',
            'password' => 'secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.guardians.store'), [
            'school_id' => $school->id,
            'full_name' => 'Guardian Phone',
            'phone' => $sharedPhone,
            'status' => 'active',
        ])->assertRedirect(route('dashboard.guardians.index'));

        $guardian = Guardian::query()->where('phone', $sharedPhone)->first();
        $this->assertNotNull($guardian);

        $guardianUser = User::query()->where('phone', '964'.$sharedPhone)->first();
        $this->assertNotNull($guardianUser);
        $this->assertSame(PhoneAccountType::Guardian->value, $guardianUser->phone_account_type);

        $response = $this->post(route('dashboard.students.store'), [
            'school_id' => $school->id,
            'full_name' => 'Ali Hassan Mahmoud',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => $sharedPhone,
            'guardian_id' => $guardian->id,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'Landmark',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('student_phone');

        $guardianUser->refresh();
        $this->assertSame(PhoneAccountType::Guardian->value, $guardianUser->phone_account_type);
    }

    private function createSchool(string $nameEn = 'Test School'): \App\Models\School
    {
        return \App\Models\School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => $nameEn,
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'St',
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(int $schoolId, string $phone, string $idCard): array
    {
        return [
            'school_id' => $schoolId,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'User',
            'age' => 30,
            'id_card_number' => $idCard,
            'license_number' => 'LIC'.substr($phone, -6),
            'primary_phone' => $phone,
            'emergency_phone' => '7900999888',
            'residential_address' => 'Address',
            'status' => 'active',
        ];
    }
}
