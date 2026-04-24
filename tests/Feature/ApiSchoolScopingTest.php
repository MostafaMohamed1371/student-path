<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\School;
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

        $this->getJson('/api/schools')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/schools')
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

        $this->postJson('/api/schools', [
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

        $this->getJson('/api/drivers/'.$driverB->id)
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

        $this->getJson('/api/schools')
            ->assertOk()
            ->assertJsonCount(2, 'data');
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
