<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTripAssignStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_students_page_and_store(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School Assign',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.315,
            'longitude' => 44.366,
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000771',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Assign Student',
            'gender' => 'male',
            'grade' => 'G1',
            'student_phone' => '7400000771',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => 33.314,
            'longitude' => 44.365,
            'shift_period' => 'MORNING',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Assign',
            'age' => 30,
            'id_card_number' => 'IDC-AS',
            'license_number' => 'LIC-AS',
            'primary_phone' => '7770000771',
            'emergency_phone' => '7770001771',
            'residential_address' => 'Addr',
            'shift_period' => 'MORNING',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Bus',
            'city' => 'Cairo',
            'number' => 'AS-1',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning route',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Start',
            'start_latitude' => 33.312,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $route->routeStudents()->create([
            'student_id' => $student->id,
            'sort_order' => 0,
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'AS-1',
            'route_title' => 'Test trip',
            'location' => 'Loc',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addHour(),
            'status' => 'PRESENT',
            'students_preview' => [],
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.trips.assign_students', [
            'school_id' => $school->id,
            'trip_id' => $trip->id,
        ]))->assertOk()->assertSee('Assign Student');

        $this->getJson(route('dashboard.trips.assign_students.form_options', [
            'school_id' => $school->id,
            'trip_id' => $trip->id,
        ]))
            ->assertOk()
            ->assertJsonPath('trip.id', $trip->id)
            ->assertJsonFragment(['id' => $student->id]);

        $this->post(route('dashboard.trips.assign_students.store'), [
            'trip_id' => $trip->id,
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
        ]);
        $this->assertSame(1, TripHistoryStudent::query()->where('trip_history_id', $trip->id)->count());
        $this->assertSame(1, $trip->fresh()->students_count);

        $this->post(route('dashboard.trips.assign_students.store'), [
            'trip_id' => $trip->id,
        ])->assertRedirect();

        $this->assertSame(0, TripHistoryStudent::query()->where('trip_history_id', $trip->id)->count());
        $this->assertSame(0, $trip->fresh()->students_count);
    }
}
