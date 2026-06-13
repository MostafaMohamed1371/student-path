<?php

namespace Tests\Feature;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Models\Absence;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbsenceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_reports_absence_to_subscribed_driver_and_notifies(): void
    {
        ['school' => $school, 'student' => $student, 'driver' => $driver, 'parent' => $parent, 'route' => $route] = $this->seedSubscribedStudent();

        Sanctum::actingAs($parent);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'travel',
            'notes' => 'Family trip',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'travel')
            ->assertJsonPath('data.reason_label_en', 'Travel')
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.transport_route_id', $route->id)
            ->assertJsonPath('data.driver_notified', true);

        $this->assertDatabaseHas('absences', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'transport_route_id' => $route->id,
            'reason' => 'travel',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $driver->user_id,
        ]);
    }

    public function test_parent_cannot_report_absence_without_subscribed_driver(): void
    {
        $school = $this->makeSchool('No Route School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G No Route',
            'phone' => '7300000101',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S No Route',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000101',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($parent);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'reason' => 'medical',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);

        $this->assertDatabaseCount('absences', 0);
    }

    public function test_driver_can_list_absences_for_assigned_students(): void
    {
        ['student' => $student, 'driver' => $driver, 'parent' => $parent] = $this->seedSubscribedStudent();
        Sanctum::actingAs($parent);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-02',
            'reason' => 'family',
        ])->assertCreated();

        Sanctum::actingAs(User::query()->findOrFail($driver->user_id));

        $this->getJson('/api/absences')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.student_id', $student->id)
            ->assertJsonPath('data.items.0.driver_id', $driver->id);
    }

    public function test_absence_marks_student_absent_on_active_trip(): void
    {
        ['school' => $school, 'student' => $student, 'driver' => $driver, 'parent' => $parent] = $this->seedSubscribedStudent();

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'BUS-1',
            'route_title' => 'Morning Route',
            'location' => 'Campus',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'PRESENT',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'status' => StudentTripStopStatus::IDLE->value,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($parent);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'medical',
        ])->assertCreated();

        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'status' => StudentTripStopStatus::ABSENT->value,
        ]);
    }

    public function test_parent_can_report_absence_after_accepted_trip_request_without_prior_route_subscription(): void
    {
        $school = $this->makeSchool('Trip Request Absence School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Trip Abs',
            'phone' => '7300000102',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Trip Abs',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000102',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $driverUser = User::factory()->create(['phone' => '9647908100102']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'IDC-TR-ABS',
            'license_number' => 'LIC-TR-ABS',
            'primary_phone' => '7770000102',
            'emergency_phone' => '7770000103',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-TR-ABS',
            'route_title' => 'Pickup route',
            'location' => 'Depot',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => StudentTripStopStatus::IDLE->value,
        ]);

        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'accepted',
            'updated_at' => now(),
        ]);

        $this->assertDatabaseMissing('transport_route_students', [
            'student_id' => $student->id,
        ]);

        Sanctum::actingAs($parent);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'medical',
        ])
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $driver->id);

        $this->assertDatabaseHas('absences', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
        ]);
    }

    public function test_meta_absence_reasons_endpoint(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/meta/absence-reasons')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data');
    }

    /**
     * @return array{school: School, student: Student, driver: Driver, parent: User, route: TransportRoute}
     */
    private function seedSubscribedStudent(): array
    {
        $school = $this->makeSchool('Absence Flow School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Abs Flow',
            'phone' => '7300000100',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Abs Flow',
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
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $driverUser = User::factory()->create(['phone' => '9647908100100']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'IDC-ABS',
            'license_number' => 'LIC-ABS',
            'primary_phone' => '7770000100',
            'emergency_phone' => '7770000101',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route Abs Flow',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot',
            'start_latitude' => 33.3,
            'start_longitude' => 44.3,
            'status' => 'active',
        ]);

        TransportRouteStudent::query()->create([
            'transport_route_id' => $route->id,
            'student_id' => $student->id,
            'sort_order' => 0,
        ]);

        return compact('school', 'student', 'driver', 'parent', 'route');
    }

    private function makeSchool(string $nameEn): School
    {
        return School::query()->create([
            'name_ar' => $nameEn,
            'name_en' => $nameEn,
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
    }
}
