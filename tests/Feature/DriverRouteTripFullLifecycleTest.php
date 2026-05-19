<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Route + trip creation (dashboard), then driver start → statuses → finalize (API).
 */
class DriverRouteTripFullLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_route_trip_start_statuses_finalize(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Lifecycle School',
            'province' => 'Baghdad',
            'district' => 'Karkh',
            'address' => 'School Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000888',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Route Trip Student',
            'gender' => 'male',
            'grade' => '3',
            'student_phone' => '7400000888',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Karkh',
            'nearest_landmark' => 'Near depot',
            'latitude' => 33.312,
            'longitude' => 44.362,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000888']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Captain',
            'father_name' => 'A',
            'grandfather_name' => 'B',
            'last_name' => 'Driver',
            'age' => 40,
            'id_card_number' => 'IDC-LC',
            'license_number' => 'LIC-LC',
            'primary_phone' => '7770000888',
            'emergency_phone' => '7770001888',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'Bus LC',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'BUS-LC',
            'color' => 'white',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('dashboard.routes.store'), [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'driver_id' => $driver->id,
            'start_address' => 'Depot Start',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ])->assertRedirect();

        $route = TransportRoute::query()->where('driver_id', $driver->id)->first();
        $this->assertNotNull($route);
        $this->assertDatabaseHas('transport_route_students', [
            'transport_route_id' => $route->id,
            'student_id' => $student->id,
        ]);

        $this->post(route('dashboard.trips.store'), [
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-LC',
            'route_title' => '',
            'location' => '',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now()->subMinutes(5)->format('Y-m-d\TH:i'),
            'status' => 'ACTIVE',
        ])->assertRedirect();

        $trip = TripHistory::query()->where('driver_id', $driver->id)->first();
        $this->assertNotNull($trip);
        $this->assertSame((int) $driver->id, (int) $trip->driver_id);

        $this->post(route('dashboard.trips.assign_students.store'), [
            'trip_id' => $trip->id,
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
        ]);

        Sanctum::actingAs($driverUser);

        $tripPublicId = 'TRP-'.$trip->id;
        $studentPublicId = 'ST-'.str_pad((string) $student->id, 3, '0', STR_PAD_LEFT);

        $this->postJson('/api/trips/'.$tripPublicId.'/start')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson('/api/update-status', [
            'student_id' => $studentPublicId,
            'new_status' => 'ON_WAY',
        ])->assertOk();

        $this->putJson('/api/update-status', [
            'student_id' => $studentPublicId,
            'new_status' => 'ARRIVED',
            'driver_lat' => 33.312,
            'driver_lng' => 44.362,
        ])->assertOk();

        $this->putJson('/api/update-status', [
            'student_id' => $studentPublicId,
            'new_status' => 'BOARDED',
        ])->assertOk();

        $this->postJson('/api/driver/trips/'.$tripPublicId.'/finalize', [
            'trip_id' => $tripPublicId,
            'driver_notes' => 'Done',
            'final_lat' => 33.312,
            'final_lng' => 44.362,
            'end_timestamp' => now()->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('trip_histories', [
            'id' => $trip->id,
            'status' => 'COMPLETED',
        ]);
    }

    public function test_start_trip_fails_without_students_or_driver(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000999']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC-EMPTY',
            'license_number' => 'LIC-EMPTY',
            'primary_phone' => '7770000999',
            'emergency_phone' => '7770001999',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $tripNoStudents = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B1',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->subMinutes(5),
            'status' => 'ACTIVE',
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/trips/TRP-'.$tripNoStudents->id.'/start')
            ->assertStatus(422);

        $tripNoDriver = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => null,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B2',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->subMinutes(5),
            'status' => 'ACTIVE',
        ]);

        $this->postJson('/api/trips/TRP-'.$tripNoDriver->id.'/start')
            ->assertStatus(403);
    }

    public function test_trip_store_requires_driver_and_students_from_route(): void
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

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-REQ',
            'license_number' => 'LIC-REQ',
            'primary_phone' => '7770000777',
            'emergency_phone' => '7770001777',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Van',
            'city' => 'Baghdad',
            'number' => 'B-REQ',
            'color' => 'white',
            'capacity' => 10,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Empty route',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Start',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('dashboard.trips.store'), [
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'B-REQ',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->format('Y-m-d\TH:i'),
            'status' => 'ACTIVE',
        ])->assertRedirect();

        $this->post(route('dashboard.trips.store'), [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'B-REQ',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->format('Y-m-d\TH:i'),
            'status' => 'ACTIVE',
        ])->assertSessionHasErrors('driver_id');
    }
}
