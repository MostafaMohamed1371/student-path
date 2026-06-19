<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Locations\IraqLocationAttributeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ProvidesDashboardIraqLocationFields;
use Tests\TestCase;

class GuardianHomeLocationStudentSyncTest extends TestCase
{
    use ProvidesDashboardIraqLocationFields;
    use RefreshDatabase;

    public function test_guardian_home_location_update_syncs_linked_students(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Home Sync School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent Sync',
            'phone' => '7900666001',
            'id_card_number' => 'PARENT-SYNC-1',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child Sync',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400666001',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Old area',
            'nearest_landmark' => 'Old landmark',
            'latitude' => 33.1,
            'longitude' => 44.1,
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $homeLocationFields = $this->dashboardIraqLocationFields('home_');
        $expectedDistrictArea = app(IraqLocationAttributeResolver::class)->label(
            (int) $homeLocationFields['home_district_id'],
            (int) $homeLocationFields['home_area_id'],
            (int) $homeLocationFields['home_neighborhood_id'],
        );

        $this->put(route('dashboard.guardians.update', $guardian), $this->withGuardianHomeIraqLocation([
            'school_id' => $school->id,
            'full_name' => $guardian->full_name,
            'phone' => $guardian->phone,
            'id_card_number' => $guardian->id_card_number,
            'status' => 'active',
            'home_latitude' => 33.3152,
            'home_longitude' => 44.3661,
            'home_nearest_landmark' => 'New pickup point',
        ]))->assertRedirect(route('dashboard.guardians.index'));

        $student->refresh();
        $this->assertSame(33.3152, (float) $student->latitude);
        $this->assertSame(44.3661, (float) $student->longitude);
        $this->assertSame($expectedDistrictArea, $student->district_area);
        $this->assertSame('New pickup point', $student->nearest_landmark);
    }

    public function test_api_home_location_update_syncs_all_children_for_parent(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School B',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        $guardianA = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Cross Parent',
            'phone' => '7900777001',
            'id_card_number' => 'CROSS-PARENT-1',
            'status' => 'active',
        ]);
        $guardianB = Guardian::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Cross Parent',
            'phone' => '7900777001',
            'id_card_number' => 'CROSS-PARENT-1',
            'status' => 'active',
        ]);

        $studentA = Student::query()->create([
            'school_id' => $schoolA->id,
            'guardian_id' => $guardianA->id,
            'full_name' => 'Child A',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400777001',
            'guardian_name' => $guardianA->full_name,
            'guardian_primary_phone' => $guardianA->phone,
            'relationship' => 'father',
            'district_area' => 'Old',
            'nearest_landmark' => 'Old',
            'latitude' => 33.0,
            'longitude' => 44.0,
            'status' => 'active',
        ]);
        $studentB = Student::query()->create([
            'school_id' => $schoolB->id,
            'guardian_id' => $guardianB->id,
            'full_name' => 'Child B',
            'gender' => 'female',
            'grade' => '2',
            'student_phone' => '7400777002',
            'guardian_name' => $guardianB->full_name,
            'guardian_primary_phone' => $guardianB->phone,
            'relationship' => 'father',
            'district_area' => 'Old',
            'nearest_landmark' => 'Old',
            'latitude' => 33.0,
            'longitude' => 44.0,
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'guardian_id' => $guardianA->id,
            'phone' => '9647900777001',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/home-location', [
            'latitude' => 33.32,
            'longitude' => 44.37,
            'district_area' => 'Shared district',
            'nearest_landmark' => 'Shared landmark',
        ])->assertStatus(201);

        $studentA->refresh();
        $studentB->refresh();

        $this->assertSame(33.32, (float) $studentA->latitude);
        $this->assertSame(44.37, (float) $studentA->longitude);
        $this->assertSame('Shared district', $studentA->district_area);
        $this->assertSame('Shared landmark', $studentA->nearest_landmark);

        $this->assertSame(33.32, (float) $studentB->latitude);
        $this->assertSame(44.37, (float) $studentB->longitude);
        $this->assertSame('Shared district', $studentB->district_area);
        $this->assertSame('Shared landmark', $studentB->nearest_landmark);
    }
}
