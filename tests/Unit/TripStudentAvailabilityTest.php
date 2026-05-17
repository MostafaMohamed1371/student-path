<?php

namespace Tests\Unit;

use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Services\Trips\TripStudentAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripStudentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_on_active_trip_is_excluded_from_other_trips(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Busy Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000001',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000001',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $freeStudent = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Free Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000002',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000002',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $tripA = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => 'B1',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now(),
            'status' => 'ACTIVE',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $tripA->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        $service = app(TripStudentAvailability::class);

        $booked = $service->studentIdsOnActiveTrips($school->id);
        $this->assertSame([(int) $student->id], $booked);
        $this->assertNotContains((int) $freeStudent->id, $booked);

        $this->expectException(ValidationException::class);
        $service->assertStudentsAvailableForTrip([(int) $student->id], $school->id);
    }

    public function test_completed_trip_releases_student(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000003',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000003',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => 'B1',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now(),
            'status' => 'COMPLETED',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        $service = app(TripStudentAvailability::class);
        $this->assertSame([], $service->studentIdsOnActiveTrips($school->id));
        $service->assertStudentsAvailableForTrip([(int) $student->id], $school->id);
        $this->assertTrue(true);
    }
}
