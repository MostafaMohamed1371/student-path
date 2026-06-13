<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\TransportRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripRequestAcceptanceReuseTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_trip_request_reuses_existing_driver_trip_instead_of_creating_duplicate(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Reuse School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000200',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Reuse Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000200',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'REUSE-DRV',
            'license_number' => 'REUSE-LIC',
            'primary_phone' => '7900222200',
            'emergency_phone' => '7900222201',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'school_id' => $school->id,
            'name' => 'Bus',
            'type' => 'Van',
            'city' => 'Baghdad',
            'number' => '145',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Baghdad route',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Baghdad / Al-Mansour / Al-Mansour center',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'status' => 'active',
        ]);

        $existingTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '145',
            'route_title' => 'Baghdad / Al-Mansour / Al-Mansour center',
            'location' => 'Depot to school',
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => now()->setTime(13, 28),
            'end_time' => now()->setTime(13, 41),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'present_type' => 'صباح',
            'notes' => 'Need seat',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $tripRequest), [
            'status' => 'accepted',
        ])->assertRedirect(route('dashboard.trip_requests.show', $tripRequest));

        $tripRequest->refresh();

        $this->assertSame('accepted', $tripRequest->status);
        $this->assertSame((int) $existingTrip->id, (int) $tripRequest->trip_history_id);
        $this->assertSame(1, TripHistory::query()->count());
        $this->assertDatabaseMissing('trip_histories', [
            'route_title' => 'Trip Request #'.$tripRequest->id,
        ]);
        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $existingTrip->id,
            'student_id' => $student->id,
        ]);

        $route = TransportRoute::query()->where('driver_id', $driver->id)->firstOrFail();
        $this->assertDatabaseHas('transport_route_students', [
            'transport_route_id' => $route->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_accepting_trip_request_subscribes_student_so_parent_can_report_absence(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Absence Subscribe School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000299',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Absence Subscribe Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000299',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'ABS-SUB-DRV',
            'license_number' => 'ABS-SUB-LIC',
            'primary_phone' => '7900222299',
            'emergency_phone' => '7900222298',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning pickup route',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'status' => 'active',
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '145',
            'route_title' => 'Morning route',
            'location' => 'Depot to school',
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => now()->setTime(7, 0),
            'end_time' => now()->setTime(8, 0),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'present_type' => 'صباح',
        ]);

        $this->assertDatabaseMissing('transport_route_students', [
            'student_id' => $student->id,
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $tripRequest), [
            'status' => 'accepted',
        ])->assertRedirect(route('dashboard.trip_requests.show', $tripRequest));

        $this->assertDatabaseHas('transport_route_students', [
            'transport_route_id' => $route->id,
            'student_id' => $student->id,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($parent);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'travel',
        ])
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.transport_route_id', $route->id);
    }

    public function test_accepting_trip_request_creates_route_when_driver_has_no_transport_route(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Auto Route School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000399',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Auto Route Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000399',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'AUTO-ROUTE-DRV',
            'license_number' => 'AUTO-ROUTE-LIC',
            'primary_phone' => '7900222399',
            'emergency_phone' => '7900222398',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $existingTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '145',
            'route_title' => 'Auto-created route title',
            'location' => 'Depot to school',
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => now()->setTime(7, 0),
            'end_time' => now()->setTime(8, 0),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'present_type' => 'صباح',
        ]);

        $this->assertDatabaseCount('transport_routes', 0);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $tripRequest), [
            'status' => 'accepted',
        ])->assertRedirect(route('dashboard.trip_requests.show', $tripRequest));

        $this->assertDatabaseCount('transport_routes', 1);
        $this->assertDatabaseHas('transport_route_students', [
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('transport_routes', [
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'name' => 'Auto-created route title',
        ]);
    }

    public function test_accepting_trip_request_uses_linked_trip_when_trip_history_id_is_set(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Linked School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000201',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Linked Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000201',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'LINK-DRV',
            'license_number' => 'LINK-LIC',
            'primary_phone' => '7900222210',
            'emergency_phone' => '7900222211',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $linkedTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-LINK',
            'route_title' => 'Linked route title',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $linkedTrip->id,
            'status' => 'pending',
            'notes' => 'Linked request',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $tripRequest), [
            'status' => 'accepted',
        ])->assertRedirect();

        $this->assertSame((int) $linkedTrip->id, (int) $tripRequest->fresh()->trip_history_id);
        $this->assertSame(1, TripHistory::query()->count());
    }

    public function test_accepting_trip_request_without_scheduled_trip_fails(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'No Trip School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000300',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'No Trip Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000300',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'NO-TRIP-DRV',
            'license_number' => 'NO-TRIP-LIC',
            'primary_phone' => '7900333300',
            'emergency_phone' => '7900333301',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
            'notes' => 'No scheduled trip',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $tripRequest), [
            'status' => 'accepted',
        ])
            ->assertRedirect(route('dashboard.trip_requests.show', $tripRequest))
            ->assertSessionHas('error');

        $this->assertSame('pending', $tripRequest->fresh()->status);
        $this->assertSame(0, TripHistory::query()->count());
    }
}
