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

class DriverTripStudentPickupOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_trip_orders_students_by_distance_from_driver_and_marks_boarded(): void
    {
        config(['trips.driver_start_early_minutes' => 10, 'trips.driver_start_late_minutes' => 10]);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Pickup School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'status' => 'active',
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
            'id_card_number' => 'IDC-ORDER',
            'license_number' => 'LIC-ORDER',
            'primary_phone' => '7770000600',
            'emergency_phone' => '7770001600',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'name' => 'Bus 1',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'B-ORDER',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $nearStudent = $this->createStudentOnTrip($school, 'Near Student', 33.311, 44.361);
        $farStudent = $this->createStudentOnTrip($school, 'Far Student', 33.35, 44.40);

        $formStart = now();
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-ORDER',
            'route_title' => 'Route',
            'location' => 'Loc',
            'students_count' => 2,
            'distance_km' => 1,
            'start_time' => $formStart,
            'end_time' => $formStart->copy()->addHour(),
            'status' => 'PRESENT',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $farStudent->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);
        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $nearStudent->id,
            'sort_order' => 1,
            'status' => 'IDLE',
        ]);

        Sanctum::actingAs($driverUser);

        $nearSid = 'ST-'.str_pad((string) $nearStudent->id, 3, '0', STR_PAD_LEFT);
        $farSid = 'ST-'.str_pad((string) $farStudent->id, 3, '0', STR_PAD_LEFT);

        $this->postJson('/api/trips/TRP-'.$trip->id.'/start', [
            'driver_lat' => 33.31,
            'driver_lng' => 44.36,
        ])
            ->assertOk()
            ->assertJsonPath('data.students.0.id', $nearSid)
            ->assertJsonPath('data.students.0.pickup_order', 1)
            ->assertJsonPath('data.students.0.is_boarded', false)
            ->assertJsonPath('data.students.1.id', $farSid)
            ->assertJsonPath('data.students.1.pickup_order', 2)
            ->assertJsonPath('data.current_active_student_id', $nearSid);

        $this->putJson('/api/update-status', [
            'student_id' => $nearSid,
            'new_status' => 'ON_WAY',
        ])->assertOk();

        $this->putJson('/api/update-status', [
            'student_id' => $nearSid,
            'new_status' => 'ARRIVED',
            'driver_lat' => 33.311,
            'driver_lng' => 44.361,
        ])->assertOk()
            ->assertJsonPath('data.trip.students.0.is_arrived', true);

        $this->putJson('/api/update-status', [
            'student_id' => $nearSid,
            'new_status' => 'BOARDED',
        ])->assertOk()
            ->assertJsonPath('data.trip.students.0.id', $farSid)
            ->assertJsonPath('data.trip.students.0.can_action', true)
            ->assertJsonPath('data.trip.students.1.id', $nearSid)
            ->assertJsonPath('data.trip.students.1.is_boarded', true)
            ->assertJsonPath('data.trip.students.1.status', 'BOARDED')
            ->assertJsonPath('data.trip.current_active_student_id', $farSid);
    }

    private function createStudentOnTrip(School $school, string $name, float $lat, float $lng): Student
    {
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => $name.' Guardian',
            'phone' => '73'.random_int(10000000, 99999999),
            'status' => 'active',
        ]);

        return Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => $name,
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '74'.random_int(10000000, 99999999),
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => $lat,
            'longitude' => $lng,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
    }
}
