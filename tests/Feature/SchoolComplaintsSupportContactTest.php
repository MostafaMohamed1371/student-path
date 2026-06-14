<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolComplaintsSupportContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_form_accepts_complaints_support_contact_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Support School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street 1',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('dashboard.schools.update', $school), [
                'name_ar' => $school->name_ar,
                'name_en' => $school->name_en,
                'province' => $school->province,
                'district' => $school->district,
                'address' => $school->address,
                'status' => 'active',
                'complaints_support_phone' => '8800',
                'complaints_support_whatsapp' => '+9647709998888',
                'complaints_support_hours' => 'Daily 8 AM - 4 PM',
            ])
            ->assertRedirect(route('dashboard.schools.index'));

        $school->refresh();
        $this->assertSame('8800', $school->complaints_support_phone);
        $this->assertSame('+9647709998888', $school->complaints_support_whatsapp);
        $this->assertSame('Daily 8 AM - 4 PM', $school->complaints_support_hours);
    }

    public function test_support_info_uses_school_contact_when_user_is_authenticated(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Parent School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street 1',
            'status' => 'active',
            'complaints_support_phone' => '8800',
            'complaints_support_whatsapp' => '+9647701112222',
            'complaints_support_hours' => 'School hours only',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent',
            'phone' => '7900111222',
            'status' => 'active',
        ]);

        Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400111222',
            'guardian_name' => 'Parent',
            'guardian_primary_phone' => '7900111222',
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'Landmark',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $user = User::factory()->create([
            'phone' => '+9647900111222',
            'guardian_id' => $guardian->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/support/info')
            ->assertOk()
            ->assertJsonPath('data.contactMethods.phone.number', '8800')
            ->assertJsonPath('data.contactMethods.phone.workingHours', 'School hours only')
            ->assertJsonPath('data.contactMethods.whatsapp.number', '+9647701112222');
    }
}
