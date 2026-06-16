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
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1TripRequestAddressInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_requests_include_driver_address_information(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Trip School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
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
            'phone' => '7300000100',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000100',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
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
            'id_card_number' => 'IDC-ADDR',
            'license_number' => 'LIC-ADDR',
            'primary_phone' => '7770000100',
            'emergency_phone' => '7770001100',
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
            'monthly_subscription_price' => 50000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($neighborhood->id);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-1',
            'route_title' => 'Route',
            'location' => 'Route',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addHour(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'pending',
            'notes' => 'Please pick up',
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/trip-requests')
            ->assertOk()
            ->assertJsonPath('data.items.0.address_information.0.id', $serviceArea->id)
            ->assertJsonPath('data.items.0.address_information.0.start_label', 'Baghdad / Karkh / Near stop')
            ->assertJsonPath('data.items.0.address_information.0.monthly_subscription_price', 50000)
            ->assertJsonPath('data.items.0.address_information.0.latitude', 33.311)
            ->assertJsonPath('data.items.0.address_information.0.longitude', 44.361);

        $this->getJson('/api/trip-requests/1')
            ->assertOk()
            ->assertJsonPath('data.address_information.0.id', $serviceArea->id);
    }
}
