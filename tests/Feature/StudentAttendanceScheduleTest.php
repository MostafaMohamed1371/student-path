<?php

namespace Tests\Feature;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Models\Absence;
use App\Models\DelayAlert;
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

class StudentAttendanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_fetch_attendance_schedule_for_owned_student(): void
    {
        ['student' => $student, 'parent' => $parent, 'school' => $school, 'driver' => $driver] = $this->seedStudentWithRoute();

        $presentTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'BUS-SCH-1',
            'route_title' => 'Morning',
            'location' => 'Campus',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => now()->startOfMonth()->addDays(2)->setTime(7, 0),
            'status' => 'PRESENT',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $presentTrip->id,
            'student_id' => $student->id,
            'status' => StudentTripStopStatus::BOARDED->value,
            'sort_order' => 0,
        ]);

        $lateTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'BUS-SCH-2',
            'route_title' => 'Morning late',
            'location' => 'Campus',
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => now()->startOfMonth()->addDays(3)->setTime(7, 0),
            'status' => 'PRESENT',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $lateTrip->id,
            'student_id' => $student->id,
            'status' => StudentTripStopStatus::BOARDED->value,
            'sort_order' => 0,
        ]);

        Absence::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'start_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'reason' => 'medical',
            'notes' => 'Dental clinic',
        ]);

        DelayAlert::query()->create([
            'trip_history_id' => $lateTrip->id,
            'driver_id' => $driver->id,
            'user_id' => $driver->user_id,
            'reason_type' => 'TRAFFIC',
            'delay_duration_minutes' => 15,
            'driver_lat' => 33.3,
            'driver_lng' => 44.3,
        ]);

        Sanctum::actingAs($parent);

        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->getJson("/api/students/{$student->id}/attendance-schedule?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.summary.present_days', 1)
            ->assertJsonPath('data.summary.absence_days', 1)
            ->assertJsonPath('data.summary.late_count', 1)
            ->assertJsonPath('data.summary.present_color', '#00796B')
            ->assertJsonPath('data.summary.absence_color', '#D32F2F')
            ->assertJsonPath('data.summary.late_color', '#5D4037')
            ->assertJsonStructure([
                'data' => [
                    'calendar',
                    'recent_events',
                    'status_legend',
                    'month_label_en',
                    'month_label_ar',
                ],
            ]);

        $calendar = $this->getJson("/api/students/{$student->id}/attendance-schedule?year={$year}&month={$month}")
            ->assertOk()
            ->json('data.calendar');

        $coloredDays = collect($calendar)->filter(fn (array $day) => $day['status'] !== null)->values();
        $this->assertSame('#00796B', collect($calendar)->firstWhere('status', 'present')['status_color'] ?? null);
        $this->assertSame('#D32F2F', collect($calendar)->firstWhere('status', 'absent')['status_color'] ?? null);
        $this->assertSame('#5D4037', collect($calendar)->firstWhere('status', 'late')['status_color'] ?? null);
        $this->assertGreaterThan(0, $coloredDays->count());
    }

    public function test_dashboard_attendance_schedule_page_renders_for_admin(): void
    {
        ['student' => $student] = $this->seedStudentWithRoute();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.students.attendance_schedule', $student))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertSee(__('dashboard.attendance_present_days'), false);
    }

    /**
     * @return array{school: School, student: Student, driver: Driver, parent: User}
     */
    private function seedStudentWithRoute(): array
    {
        $school = School::query()->create([
            'name_ar' => 'School',
            'name_en' => 'School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000200',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Schedule Student',
            'gender' => 'male',
            'grade' => '4',
            'student_phone' => '7400000200',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647908200200']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'R',
            'grandfather_name' => 'V',
            'last_name' => 'R',
            'age' => 35,
            'id_card_number' => 'IDC-SCH',
            'license_number' => 'LIC-SCH',
            'primary_phone' => '7770000200',
            'emergency_phone' => '7770000201',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $route = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route',
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
