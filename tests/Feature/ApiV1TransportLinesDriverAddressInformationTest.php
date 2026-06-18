<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Bus;
use App\Models\District;
use App\Models\Driver;
use App\Models\DriverServiceArea;
use App\Models\Guardian;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1TransportLinesDriverAddressInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transport_lines_drivers_include_address_information(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Lines School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 0]);
        $neighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Near stop',
            'sort_order' => 0,
            'latitude' => 33.311,
            'longitude' => 44.361,
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent',
            'phone' => '7300000200',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000200',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => 33.312,
            'longitude' => 44.362,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 35,
            'id_card_number' => 'IDC-LINES',
            'license_number' => 'LIC-LINES',
            'primary_phone' => '7770000200',
            'emergency_phone' => '7770001200',
            'residential_address' => 'Driver home',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'name' => 'Bus 1',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 75000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($neighborhood->id);

        $otherNeighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Other area',
            'sort_order' => 1,
            'latitude' => 33.40,
            'longitude' => 44.50,
        ]);
        $otherServiceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 90000,
            'sort_order' => 1,
        ]);
        $otherServiceArea->neighborhoods()->attach($otherNeighborhood->id);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning route',
            'trip_type' => 'MORNING_PICKUP',
            'shift_period' => 'MORNING',
            'start_address' => 'Near stop',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.drivers.0.driverId', (string) $driver->id)
            ->assertJsonPath('data.drivers.0.address_information.0.id', $serviceArea->id)
            ->assertJsonPath('data.drivers.0.address_information.0.start_label', 'Baghdad / Karkh / Near stop')
            ->assertJsonPath('data.drivers.0.address_information.0.monthly_subscription_price', 75000)
            ->assertJsonCount(1, 'data.drivers.0.address_information');

        $this->getJson('/api/transport-lines/drivers/'.$driver->id.'?student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.driver.address_information.0.id', $serviceArea->id)
            ->assertJsonCount(1, 'data.driver.address_information');
    }

    public function test_transport_lines_drivers_show_all_address_information_when_parent_sub_district_differs(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Lines School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 0]);
        $driverNeighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Driver stop',
            'sort_order' => 0,
            'latitude' => 33.311,
            'longitude' => 44.361,
        ]);
        Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Parent stop',
            'sort_order' => 1,
            'latitude' => 33.20,
            'longitude' => 44.20,
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent',
            'phone' => '7300000300',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000300',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => 33.20,
            'longitude' => 44.20,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 35,
            'id_card_number' => 'IDC-LINES2',
            'license_number' => 'LIC-LINES2',
            'primary_phone' => '7770000500',
            'emergency_phone' => '7770001500',
            'residential_address' => 'Driver home',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'name' => 'Bus 1',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 75000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($driverNeighborhood->id);

        $otherNeighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Other area',
            'sort_order' => 2,
            'latitude' => 33.40,
            'longitude' => 44.50,
        ]);
        $otherServiceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 90000,
            'sort_order' => 1,
        ]);
        $otherServiceArea->neighborhoods()->attach($otherNeighborhood->id);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning route',
            'trip_type' => 'MORNING_PICKUP',
            'shift_period' => 'MORNING',
            'start_address' => 'Driver stop',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id.'&matches_route_only=0')
            ->assertOk()
            ->assertJsonCount(2, 'data.drivers.0.address_information');
    }
}
