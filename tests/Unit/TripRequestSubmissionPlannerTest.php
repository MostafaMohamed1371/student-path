<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Trips\TripRequestSubmissionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripRequestSubmissionPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_driver_from_selected_trip_when_shift_matches(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7900000011',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7900000012',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Area A',
            'nearest_landmark' => 'Landmark B',
            'shift_period' => 'MORNING',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id]);

        $tripDriver = Driver::query()->create($this->driverPayload($school->id, 'MORNING', 'TRIP-DRV'));
        Driver::query()->create($this->driverPayload($school->id, 'MORNING', 'OTHER-DRV'));

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $tripDriver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B1',
            'route_title' => 'Route',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $plan = app(TripRequestSubmissionPlanner::class)->plan($parent, $student, $trip);

        $this->assertSame($tripDriver->id, $plan->driverId);
        $this->assertSame('MORNING', $plan->targetShift);
        $this->assertStringContainsString('Area A', (string) $plan->snapshot['moving_point']);
        $this->assertSame($trip->id, $plan->tripHistoryId);
    }

    public function test_rejects_trip_when_student_shift_does_not_match(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7900000021',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7900000022',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'shift_period' => 'EVENING',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id]);
        $driver = Driver::query()->create($this->driverPayload($school->id, 'MORNING', 'DRV-M'));
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B1',
            'route_title' => 'Route',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $this->expectException(ValidationException::class);

        app(TripRequestSubmissionPlanner::class)->plan($parent, $student, $trip);
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(int $schoolId, string $shift, string $idCard): array
    {
        return [
            'school_id' => $schoolId,
            'first_name' => 'D',
            'father_name' => 'R',
            'grandfather_name' => 'X',
            'last_name' => 'Y',
            'age' => 30,
            'id_card_number' => $idCard,
            'license_number' => 'LIC-'.$idCard,
            'primary_phone' => '7500'.substr($idCard, -6),
            'emergency_phone' => '7501'.substr($idCard, -6),
            'residential_address' => 'Addr',
            'shift_period' => $shift,
            'status' => 'active',
        ];
    }
}
