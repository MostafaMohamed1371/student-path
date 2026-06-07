<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUserPartialUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_user_only_persists_changed_fields(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'name' => 'Original Name',
            'phone' => '9647901111111',
            'city' => 'Baghdad',
            'licence_number' => 'LIC-KEEP',
            'votes' => 10,
            'rate' => 4.5,
            'is_verified' => true,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Original',
            'father_name' => 'Name',
            'grandfather_name' => 'Keep',
            'last_name' => 'Same',
            'age' => 30,
            'id_card_number' => 'DRV-PARTIAL',
            'license_number' => 'LIC-KEEP',
            'primary_phone' => '7901111111',
            'emergency_phone' => '7901111112',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $this->put(route('dashboard.users.update', $user), [
            'name' => 'Original Name',
            'school_id' => $school->id,
            'phone' => '7901111111',
            'city' => 'Basra',
            'licence_number' => 'LIC-KEEP',
            'votes' => 10,
            'rate' => 4.5,
            'is_verified' => 1,
            'is_admin' => 0,
            'is_active' => 1,
            'password' => '',
        ])->assertRedirect(route('dashboard.users.index'));

        $user->refresh();
        $driver->refresh();

        $this->assertSame('Basra', $user->city);
        $this->assertSame('Original Name', $user->name);
        $this->assertSame(10, $user->votes);
        $this->assertTrue($user->is_verified);
        $this->assertTrue($user->is_active);

        $this->assertSame('Basra', $driver->residential_address);
        $this->assertSame('Original', $driver->first_name);
        $this->assertSame('Keep', $driver->grandfather_name);
        $this->assertSame('7901111111', $driver->primary_phone);
    }
}
