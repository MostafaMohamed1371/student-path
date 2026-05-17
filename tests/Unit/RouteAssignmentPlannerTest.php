<?php

namespace Tests\Unit;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\User;
use App\Services\Routes\RouteAssignmentPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAssignmentPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_along_route_matches_students_between_start_and_school(): void
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

        $user1 = User::factory()->create();
        $driver1 = $this->makeDriver($school->id, $user1->id, 'D1');
        Bus::query()->create([
            'user_id' => $user1->id,
            'driver_id' => $driver1->id,
            'name' => 'Bus 1',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'white',
            'capacity' => 10,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver1->id,
            'name' => 'Route D1',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot 1',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $this->makeStudent($school->id, 'Near', 33.312, 44.362);
        $this->makeStudent($school->id, 'Far', 33.40, 44.50);

        $result = app(RouteAssignmentPlanner::class)->assignStudentsAlongRoute($route);

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(1, $result['skipped_off_corridor']);
        $this->assertSame(1, TransportRouteStudent::query()->count());
    }

    public function test_student_eligible_when_on_route_or_in_corridor(): void
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

        $user1 = User::factory()->create();
        $driver1 = $this->makeDriver($school->id, $user1->id, 'D1');

        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver1->id,
            'name' => 'Route D1',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot 1',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $near = $this->makeStudent($school->id, 'Near', 33.312, 44.362);
        $far = $this->makeStudent($school->id, 'Far', 33.40, 44.50);

        TransportRouteStudent::query()->create([
            'transport_route_id' => $route->id,
            'student_id' => $far->id,
            'sort_order' => 0,
            'distance_from_school_km' => 5,
        ]);

        $planner = app(RouteAssignmentPlanner::class);

        $this->assertTrue($planner->studentEligibleForDriverRoute($near, $route));
        $this->assertTrue($planner->studentEligibleForDriverRoute($far, $route));

        $filtered = $planner->filterStudentsForDriverRoute(collect([$near, $far]), $route);
        $this->assertCount(2, $filtered);
    }

    public function test_auto_assign_uses_start_to_school_corridor(): void
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

        $user1 = User::factory()->create();
        $driver1 = $this->makeDriver($school->id, $user1->id, 'D1');
        Bus::query()->create([
            'user_id' => $user1->id,
            'driver_id' => $driver1->id,
            'name' => 'Bus 1',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'white',
            'capacity' => 10,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver1->id,
            'name' => 'Route D1',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot 1',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $this->makeStudent($school->id, 'Near', 33.312, 44.362);
        $this->makeStudent($school->id, 'Far', 33.40, 44.50);

        $result = app(RouteAssignmentPlanner::class)->autoAssignForSchoolTripType(
            $school->id,
            TripType::MORNING_PICKUP->value,
        );

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(1, TransportRouteStudent::query()->count());
    }

    private function makeDriver(int $schoolId, int $userId, string $suffix): Driver
    {
        return Driver::query()->create([
            'user_id' => $userId,
            'school_id' => $schoolId,
            'first_name' => $suffix,
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-'.$suffix,
            'license_number' => 'LIC-'.$suffix,
            'primary_phone' => '7770000'.substr($suffix, -1),
            'emergency_phone' => '7770001'.substr($suffix, -1),
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
    }

    private function makeStudent(int $schoolId, string $name, float $lat, float $lng): Student
    {
        return Student::query()->create([
            'school_id' => $schoolId,
            'full_name' => $name,
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000'.random_int(100, 999),
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000'.random_int(100, 999),
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => $lat,
            'longitude' => $lng,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
    }
}
