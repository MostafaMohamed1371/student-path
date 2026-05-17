<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
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

    public function test_form_options_includes_transport_route_for_driver(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'School Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
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
            'id_card_number' => 'IDC-TR',
            'license_number' => 'LIC-TR',
            'primary_phone' => '7770000500',
            'emergency_phone' => '7770000501',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'BUS-R',
            'color' => 'white',
            'capacity' => 12,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning Line',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Start Point',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
        ]))
            ->assertOk()
            ->assertJsonPath('drivers.0.transport_route_id', TransportRoute::query()->value('id'))
            ->assertJsonPath('drivers.0.route_title', 'Morning Line — Start Point')
            ->assertJsonPath('drivers.0.start_address', 'Start Point')
            ->assertJsonPath('drivers.0.end_address', 'School Campus');

        $distanceKm = $this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
        ]))->json('drivers.0.distance_km');

        $this->assertIsNumeric($distanceKm);
        $this->assertGreaterThan(0, (float) $distanceKm);
    }

    public function test_form_options_hides_students_on_other_active_trips(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $busy = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Busy',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000200',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000200',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $free = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Free',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000201',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000201',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => 'B1',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now(),
            'trip_type' => 'MORNING_PICKUP',
            'status' => 'ACTIVE',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $busy->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $ids = collect($this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => 'MORNING_PICKUP',
        ]))->json('students'))->pluck('id')->all();

        $this->assertNotContains($busy->id, $ids);
        $this->assertContains($free->id, $ids);
    }

    public function test_form_options_filters_students_by_driver_route_corridor(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.31,
            'longitude' => 44.36,
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
            'id_card_number' => 'IDC-COR',
            'license_number' => 'LIC-COR',
            'primary_phone' => '7770000600',
            'emergency_phone' => '7770000601',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Line',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $near = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Near',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000300',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000300',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => 33.312,
            'longitude' => 44.362,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $far = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Far',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000301',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000301',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => 33.40,
            'longitude' => 44.50,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $withoutDriver = collect($this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
        ]))->json('students'))->pluck('id')->all();

        $this->assertContains($near->id, $withoutDriver);
        $this->assertContains($far->id, $withoutDriver);

        $this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'driver_id' => $driver->id,
        ]))
            ->assertOk()
            ->assertJsonPath('route_filter_active', true)
            ->assertJsonPath('corridor_max_km', 3);

        $withDriver = collect($this->getJson(route('dashboard.trips.form_options', [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'driver_id' => $driver->id,
        ]))->json('students'))->pluck('id')->all();

        $this->assertContains($near->id, $withDriver);
        $this->assertNotContains($far->id, $withDriver);
    }
}
