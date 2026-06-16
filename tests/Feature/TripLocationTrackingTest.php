<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'trips.location_store' => 'cache',
            'realtime.firebase.database_url' => 'https://example.firebaseio.com',
        ]);
    }

    public function test_driver_posts_location_and_parent_gets_tracking_with_distance(): void
    {
        $school = School::query()->create([
            'name_ar' => 'School',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Addr',
            'latitude' => 33.300000,
            'longitude' => 44.300000,
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent',
            'phone' => '9647900111222',
            'status' => 'active',
        ]);

        $parentUser = User::factory()->create([
            'phone' => '9647900111223',
            'guardian_id' => $guardian->id,
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => 'G1',
            'student_phone' => '7400000111',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => 33.312000,
            'longitude' => 44.362000,
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000111']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'H',
            'grandfather_name' => 'H',
            'last_name' => 'Driver',
            'age' => 35,
            'id_card_number' => 'IDC-LOC',
            'license_number' => 'LIC-LOC',
            'primary_phone' => '7770000111',
            'emergency_phone' => '7770000112',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-LOC',
            'route_title' => 'Route A',
            'location' => 'Loc',
            'students_count' => 1,
            'distance_km' => 5,
            'start_time' => now()->subMinutes(5),
            'driver_started_at' => now()->subMinutes(2),
            'status' => 'ACTIVE',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'ON_WAY',
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/driver/trips/TRP-'.$trip->id.'/location', [
            'latitude' => 33.315000,
            'longitude' => 44.365000,
            'heading' => 90,
            'speed_kmh' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('data.location.latitude', 33.315)
            ->assertJsonPath('data.firebase_path', 'trips/'.$trip->id.'/tracking');

        Sanctum::actingAs($parentUser);

        $this->getJson('/api/trips/TRP-'.$trip->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.tracking_active', true)
            ->assertJsonPath('data.driver.name', 'Ali H H Driver')
            ->assertJsonPath('data.bus.number', 'B-LOC') // from trip.bus_number fallback
            ->assertJsonPath('data.location.latitude', 33.315)
            ->assertJsonPath('data.distance.reference.type', 'student_pickup')
            ->assertJsonPath('data.realtime.firebase_path', 'trips/'.$trip->id.'/tracking/location');

        $meters = $this->getJson('/api/trips/TRP-'.$trip->id.'/tracking')->json('data.distance.meters');
        $this->assertIsNumeric($meters);
        $this->assertGreaterThan(0, $meters);

        $this->getJson('/api/trips/'.$trip->id.'/tracking/location')
            ->assertOk()
            ->assertJsonPath('data.tracking_active', true)
            ->assertJsonPath('data.location.latitude', 33.315)
            ->assertJsonPath('data.firebase_path', 'trips/'.$trip->id.'/tracking/location');
    }

    public function test_driver_cannot_post_location_before_trip_started(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC',
            'license_number' => 'LIC',
            'primary_phone' => '1',
            'emergency_phone' => '2',
            'residential_address' => 'A',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => '1',
            'route_title' => 'R',
            'students_count' => 0,
            'start_time' => now(),
            'driver_started_at' => null,
            'status' => 'ACTIVE',
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/driver/trips/'.$trip->id.'/location', [
            'latitude' => 33.0,
            'longitude' => 44.0,
        ])->assertStatus(422);
    }

    public function test_driver_can_update_planned_pickup_start_before_trip_started(): void
    {
        $school = School::query()->create([
            'name_ar' => 'School',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'School Address',
            'latitude' => 33.300000,
            'longitude' => 44.300000,
            'status' => 'active',
            'work_days' => [strtolower(now()->locale('en')->dayName)],
            'shift_period' => 'MORNING',
            'work_time_from' => '07:30',
            'work_time_to' => '14:00',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC-PLANNED-1',
            'license_number' => 'LIC-PLANNED-1',
            'primary_phone' => '1',
            'emergency_phone' => '2',
            'residential_address' => 'Depot',
            'status' => 'active',
        ]);

        $pickup = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-1',
            'route_title' => 'Route A',
            'location' => 'Old location',
            'start_address' => 'Depot',
            'start_latitude' => 33.31,
            'start_longitude' => 44.31,
            'students_count' => 0,
            'distance_km' => 1.11,
            'start_time' => now()->addHour()->toDateTimeString(),
            'end_time' => now()->addHour()->addMinutes(40)->toDateTimeString(),
            'status' => 'PRESENT',
            'driver_started_at' => null,
        ]);

        $pairPlanner = app(\App\Services\Trips\PickupReturnTripPairPlanner::class);
        $returnAttributes = $pairPlanner->returnTripAttributesFromPickup(
            $pairPlanner->pickupAttributesFromTrip($pickup),
            $school,
        );

        $return = TripHistory::query()->create($returnAttributes);

        Sanctum::actingAs($driverUser);

        $newLat = 33.315000;
        $newLng = 44.340000;

        $this->postJson('/api/driver/trips/TRP-'.$pickup->id.'/location', [
            'latitude' => $newLat,
            'longitude' => $newLng,
        ])
            ->assertCreated()
            ->assertJsonPath('data.planned_start_updated', true);

        $pickup->refresh();
        $return->refresh();

        $this->assertEqualsWithDelta((float) $newLat, (float) $pickup->start_latitude, 0.0000001);
        $this->assertEqualsWithDelta((float) $newLng, (float) $pickup->start_longitude, 0.0000001);

        $expectedDistance = round(
            \App\Support\Geo\Haversine::metersBetween(
                $newLat,
                $newLng,
                (float) $school->latitude,
                (float) $school->longitude,
            ) / 1000,
            2,
        );

        $this->assertEqualsWithDelta((float) $expectedDistance, (float) $pickup->distance_km, 0.000001);
        $this->assertEqualsWithDelta((float) $expectedDistance, (float) $return->distance_km, 0.000001);
    }

    public function test_parent_forbidden_when_not_on_trip(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $otherParent = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC',
            'license_number' => 'LIC',
            'primary_phone' => '1',
            'emergency_phone' => '2',
            'residential_address' => 'A',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => '1',
            'route_title' => 'R',
            'students_count' => 0,
            'start_time' => now(),
            'driver_started_at' => now(),
            'status' => 'ACTIVE',
        ]);

        Sanctum::actingAs($otherParent);

        $this->getJson('/api/trips/TRP-'.$trip->id.'/tracking')->assertStatus(403);
    }
}
