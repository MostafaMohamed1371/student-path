<?php

namespace Tests\Unit;

use App\Models\Area;
use App\Models\Bus;
use App\Models\District;
use App\Models\Driver;
use App\Models\DriverServiceArea;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\TransportRoute;
use App\Models\User;
use App\Services\Drivers\DriverServiceAreaTripFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverServiceAreaAddressInformationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_to_matching_sub_district_when_parent_matches_driver_area(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 0]);
        $nearStop = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Near stop',
            'sort_order' => 0,
            'latitude' => 33.311,
            'longitude' => 44.361,
        ]);
        $farStop = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Far stop',
            'sort_order' => 1,
            'latitude' => 33.40,
            'longitude' => 44.50,
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
            'id_card_number' => 'IDC-FILTER',
            'license_number' => 'LIC-FILTER',
            'primary_phone' => '7770000300',
            'emergency_phone' => '7770001300',
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

        $nearArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 50000,
            'sort_order' => 0,
        ]);
        $nearArea->neighborhoods()->attach($nearStop->id);

        $farArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 80000,
            'sort_order' => 1,
        ]);
        $farArea->neighborhoods()->attach($farStop->id);

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

        $formatter = app(DriverServiceAreaTripFormatter::class);
        $all = $formatter->addressInformationByDriverIds([(int) $driver->id])[(int) $driver->id];

        $this->assertCount(2, $all);

        $filtered = $formatter->filterAddressInformationForPickupNeighborhood($all, (int) $nearStop->id);

        $this->assertCount(1, $filtered);
        $this->assertSame((int) $nearArea->id, $filtered[0]['id']);
    }

    public function test_returns_all_areas_when_parent_sub_district_does_not_match_driver(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 0]);
        $driverStop = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Driver stop',
            'sort_order' => 0,
            'latitude' => 33.311,
            'longitude' => 44.361,
        ]);
        $parentStop = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Parent stop',
            'sort_order' => 1,
            'latitude' => 33.20,
            'longitude' => 44.20,
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
            'id_card_number' => 'IDC-FILTER2',
            'license_number' => 'LIC-FILTER2',
            'primary_phone' => '7770000400',
            'emergency_phone' => '7770001400',
            'residential_address' => 'Driver home',
            'status' => 'active',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 50000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($driverStop->id);

        $formatter = app(DriverServiceAreaTripFormatter::class);
        $all = $formatter->addressInformationByDriverIds([(int) $driver->id])[(int) $driver->id];

        $filtered = $formatter->filterAddressInformationForPickupNeighborhood($all, (int) $parentStop->id);

        $this->assertCount(1, $filtered);
        $this->assertSame((int) $serviceArea->id, $filtered[0]['id']);
    }
}
