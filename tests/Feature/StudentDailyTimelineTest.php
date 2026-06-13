<?php

namespace Tests\Feature;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Models\Absence;
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

class StudentDailyTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_fetch_daily_timeline_with_milestones_and_colors(): void
    {
        ['student' => $student, 'parent' => $parent, 'school' => $school, 'driver' => $driver] = $this->seedStudentWithRoute();
        $day = now()->startOfDay();

        $morningTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-TL-1',
            'route_title' => 'Morning Route',
            'location' => 'Campus',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => $day->copy()->setTime(7, 15),
            'end_time' => $day->copy()->setTime(7, 45),
            'status' => 'COMPLETED',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $morningTrip->id,
            'student_id' => $student->id,
            'status' => StudentTripStopStatus::BOARDED->value,
            'boarding_time' => $day->copy()->setTime(7, 18),
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/students/'.$student->id.'/daily-timeline?date='.$day->toDateString())
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.is_absent_today', false)
            ->assertJsonCount(4, 'data.milestones')
            ->assertJsonPath('data.milestones.0.code', 'morning_pickup_home')
            ->assertJsonPath('data.milestones.0.status', 'boarded')
            ->assertJsonPath('data.milestones.0.status_color', '#00796B')
            ->assertJsonPath('data.milestones.0.scheduled_time', '07:15')
            ->assertJsonPath('data.milestones.1.code', 'morning_arrive_school')
            ->assertJsonPath('data.milestones.1.status', 'completed')
            ->assertJsonPath('data.milestones.1.scheduled_time', '07:45')
            ->assertJsonPath('data.milestones.2.status', 'scheduled')
            ->assertJsonPath('data.milestones.2.status_color', '#78909C');
    }

    public function test_daily_timeline_evening_milestones_use_return_trip_start_and_end_times(): void
    {
        ['student' => $student, 'parent' => $parent, 'school' => $school, 'driver' => $driver] = $this->seedStudentWithRoute();
        $day = now()->startOfDay();

        $returnTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_RETURN->value,
            'bus_number' => 'BUS-TL-RET',
            'route_title' => 'Return Route',
            'location' => 'School to home',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => $day->copy()->setTime(12, 30),
            'end_time' => $day->copy()->setTime(13, 15),
            'status' => 'ACTIVE',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $returnTrip->id,
            'student_id' => $student->id,
            'status' => StudentTripStopStatus::ON_WAY->value,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/students/'.$student->id.'/daily-timeline?date='.$day->toDateString())
            ->assertOk()
            ->assertJsonPath('data.milestones.2.code', 'evening_pickup_school')
            ->assertJsonPath('data.milestones.2.scheduled_time', '12:30')
            ->assertJsonPath('data.milestones.2.status', 'on_way')
            ->assertJsonPath('data.milestones.3.code', 'evening_arrive_home')
            ->assertJsonPath('data.milestones.3.scheduled_time', '13:15')
            ->assertJsonPath('data.milestones.3.status', 'scheduled');
    }

    public function test_daily_timeline_uses_driver_trip_times_when_student_not_on_roster(): void
    {
        ['student' => $student, 'parent' => $parent, 'school' => $school, 'driver' => $driver] = $this->seedStudentWithRoute();
        $day = now()->startOfDay();

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-TL-2',
            'route_title' => 'Morning Route',
            'location' => 'Campus',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => $day->copy()->setTime(6, 40),
            'end_time' => $day->copy()->setTime(7, 10),
            'status' => 'ACTIVE',
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_RETURN->value,
            'bus_number' => 'BUS-TL-3',
            'route_title' => 'Return Route',
            'location' => 'School to home',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => $day->copy()->setTime(14, 5),
            'end_time' => $day->copy()->setTime(14, 50),
            'status' => 'ACTIVE',
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/students/'.$student->id.'/daily-timeline?date='.$day->toDateString())
            ->assertOk()
            ->assertJsonPath('data.milestones.0.code', 'morning_pickup_home')
            ->assertJsonPath('data.milestones.0.scheduled_time', '06:40')
            ->assertJsonPath('data.milestones.0.status', 'scheduled')
            ->assertJsonPath('data.milestones.1.code', 'morning_arrive_school')
            ->assertJsonPath('data.milestones.1.scheduled_time', '07:10')
            ->assertJsonPath('data.milestones.2.code', 'evening_pickup_school')
            ->assertJsonPath('data.milestones.2.scheduled_time', '14:05')
            ->assertJsonPath('data.milestones.3.code', 'evening_arrive_home')
            ->assertJsonPath('data.milestones.3.scheduled_time', '14:50');
    }

    public function test_daily_timeline_marks_milestones_absent_when_parent_reported(): void
    {
        ['student' => $student, 'parent' => $parent, 'driver' => $driver] = $this->seedStudentWithRoute();
        $day = now()->startOfDay();

        Absence::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'reason' => 'medical',
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/students/'.$student->id.'/daily-timeline')
            ->assertOk()
            ->assertJsonPath('data.is_absent_today', true)
            ->assertJsonPath('data.milestones.0.status', 'absent')
            ->assertJsonPath('data.milestones.0.status_color', '#D32F2F');
    }

    public function test_dashboard_daily_timeline_page_renders(): void
    {
        ['student' => $student] = $this->seedStudentWithRoute();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.students.daily_timeline', $student))
            ->assertOk()
            ->assertSee(__('dashboard.daily_timeline_milestones'), false)
            ->assertSee($student->full_name, false);
    }

    /**
     * @return array{school: School, student: Student, driver: Driver, parent: User}
     */
    private function seedStudentWithRoute(): array
    {
        $school = School::query()->create([
            'name_ar' => 'إعدادية بغداد للبنين',
            'name_en' => 'Baghdad Prep Boys',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000300',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Liam Abdullah Mohammed',
            'gender' => 'male',
            'grade' => '4',
            'student_phone' => '7400000300',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Home area',
            'nearest_landmark' => 'Near school',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647908300300']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'R',
            'grandfather_name' => 'V',
            'last_name' => 'R',
            'age' => 35,
            'id_card_number' => 'IDC-TL',
            'license_number' => 'LIC-TL',
            'primary_phone' => '7770000300',
            'emergency_phone' => '7770000301',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route TL',
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

        return compact('school', 'student', 'driver', 'parent');
    }
}
