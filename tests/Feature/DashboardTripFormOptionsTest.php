<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTripFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_options_includes_driver_bus_number_and_capacity(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'H',
            'grandfather_name' => 'H',
            'last_name' => 'Driver',
            'age' => 35,
            'id_card_number' => 'IDC-TRIP-FO',
            'license_number' => 'LIC-TRIP-FO',
            'primary_phone' => '7770000100',
            'emergency_phone' => '7770000101',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'Bus 1',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'BUS-42',
            'color' => 'white',
            'capacity' => 24,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => 'MORNING_PICKUP',
        ]))
            ->assertOk()
            ->assertJsonPath('drivers.0.id', $driver->id)
            ->assertJsonPath('drivers.0.bus_number', 'BUS-42')
            ->assertJsonPath('drivers.0.students_count', 24);
    }
}
