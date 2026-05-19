<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBusFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_options_returns_drivers_for_selected_school_without_bus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

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

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $driverA = Driver::query()->create([
            'user_id' => $userA->id,
            'school_id' => $schoolA->id,
            'first_name' => 'Ali',
            'father_name' => 'A',
            'grandfather_name' => 'A',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-A',
            'license_number' => 'LIC-A',
            'primary_phone' => '7770000101',
            'emergency_phone' => '7770001101',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        Driver::query()->create([
            'user_id' => $userB->id,
            'school_id' => $schoolB->id,
            'first_name' => 'Bob',
            'father_name' => 'B',
            'grandfather_name' => 'B',
            'last_name' => 'Two',
            'age' => 30,
            'id_card_number' => 'IDC-B',
            'license_number' => 'LIC-B',
            'primary_phone' => '7770000102',
            'emergency_phone' => '7770001102',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.buses.form_options', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertJsonCount(1, 'drivers')
            ->assertJsonPath('drivers.0.id', $driverA->id);

        Bus::query()->create([
            'user_id' => $userA->id,
            'driver_id' => $driverA->id,
            'name' => 'Bus A',
            'type' => 'Bus',
            'city' => 'Cairo',
            'number' => 'A-1',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.buses.form_options', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertJsonCount(0, 'drivers');
    }
}
