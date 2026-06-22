<?php

namespace Tests\Feature;

use App\Enums\PhoneAccountType;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_get_returns_email_and_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'parent@example.com',
            'phone' => '9647701234567',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'parent@example.com')
            ->assertJsonPath('data.phone', '9647701234567')
            ->assertJsonPath('data.phoneFromAccount', '9647701234567')
            ->assertJsonStructure([
                'data' => [
                    'userId',
                    'type_user',
                    'nameFromAccount',
                    'driver',
                    'school',
                    'guardian',
                    'preferredLanguage',
                    'isActive',
                ],
            ]);
    }

    public function test_profile_get_returns_type_user_for_guardian(): void
    {
        $school = School::query()->create([
            'name_en' => 'School',
            'name_ar' => 'مدرسة',
            'province' => 'Baghdad',
            'district' => 'Karrada',
            'address' => 'Street 1',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent One',
            'phone' => '7701234567',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
            'phone' => '9647701234567',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.type_user', 'guardian')
            ->assertJsonPath('data.guardian.id', $guardian->id)
            ->assertJsonPath('data.school.id', $school->id);
    }

    public function test_profile_update_persists_email_and_phone(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '9647701234567',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'email' => 'updated@example.com',
            'phone' => '7901234567',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.phone', '9647901234567');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
            'phone' => '9647901234567',
        ]);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => null]);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_rejects_phone_already_used(): void
    {
        User::factory()->create(['phone' => '9647901111111']);
        $user = User::factory()->create(['phone' => '9647701234567']);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['phone' => '7901111111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_profile_phone_update_syncs_linked_guardian_and_students(): void
    {
        $school = School::query()->create([
            'name_en' => 'Test School',
            'name_ar' => 'مدرسة',
            'province' => 'Baghdad',
            'district' => 'Karrada',
            'address' => 'Street 1',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent One',
            'phone' => '7701234567',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'phone' => '9647701234567',
            'guardian_id' => $guardian->id,
            'phone_account_type' => PhoneAccountType::Guardian->value,
            'school_id' => $school->id,
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child One',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000101',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => '7701234567',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['phone' => '7909998888'])
            ->assertOk()
            ->assertJsonPath('data.phone', '9647909998888');

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'phone' => '7909998888',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'guardian_primary_phone' => '7909998888',
        ]);
    }
}
