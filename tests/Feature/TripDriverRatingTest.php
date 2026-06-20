<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripDriverRating;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripDriverRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_rate_driver_after_completed_trip(): void
    {
        ['parent' => $parent, 'trip' => $trip, 'driver' => $driver, 'student' => $student] = $this->seedCompletedTrip();

        Sanctum::actingAs($parent);

        $this->postJson('/api/trips/'.$trip->id.'/rate-driver', [
            'rating' => 5,
            'comment' => 'Excellent driver',
            'student_id' => $student->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Excellent driver')
            ->assertJsonPath('data.updated', false)
            ->assertJsonPath('data.driver_rating_avg', 5)
            ->assertJsonPath('data.driver_rating_count', 1);

        $this->assertDatabaseHas('trip_driver_ratings', [
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'rating' => 5,
        ]);

        $driver->load('user');
        $this->assertSame(5.0, (float) $driver->user->rate);
        $this->assertSame(1, (int) $driver->user->votes);
    }

    public function test_parent_can_update_existing_trip_rating(): void
    {
        ['parent' => $parent, 'trip' => $trip, 'driver' => $driver, 'student' => $student] = $this->seedCompletedTrip();

        TripDriverRating::query()->create([
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'rating' => 3,
            'comment' => 'OK',
        ]);

        Sanctum::actingAs($parent);

        $this->postJson('/api/trips/'.$trip->id.'/rate-driver', [
            'rating' => 4,
            'comment' => 'Better than expected',
        ])
            ->assertOk()
            ->assertJsonPath('data.updated', true)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.driver_rating_avg', 4)
            ->assertJsonPath('data.driver_rating_count', 1);

        $this->assertSame(1, TripDriverRating::query()->where('trip_history_id', $trip->id)->count());
    }

    public function test_parent_cannot_rate_trip_before_completion(): void
    {
        ['parent' => $parent, 'trip' => $trip] = $this->seedCompletedTrip(status: 'PRESENT');

        Sanctum::actingAs($parent);

        $this->postJson('/api/trips/'.$trip->id.'/rate-driver', ['rating' => 5])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_parent_cannot_rate_trip_without_own_child_on_it(): void
    {
        ['trip' => $trip] = $this->seedCompletedTrip();
        $otherParent = User::factory()->create();

        Sanctum::actingAs($otherParent);

        $this->postJson('/api/trips/'.$trip->id.'/rate-driver', ['rating' => 5])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_multiple_parent_ratings_recalculate_driver_average(): void
    {
        $school = $this->makeSchool('Rating School');
        $driverUser = User::factory()->create(['phone' => '9647909111111', 'rate' => 0, 'votes' => 0]);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'R',
            'age' => 30,
            'id_card_number' => 'IDC-RATE',
            'license_number' => 'LIC-RATE',
            'primary_phone' => '7909111111',
            'emergency_phone' => '7909111112',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $parentA = $this->makeParentWithStudent($school, '7300000101', '7400000101');
        $parentB = $this->makeParentWithStudent($school, '7300000102', '7400000102');

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-1',
            'route_title' => 'Route',
            'location' => 'Baghdad',
            'students_count' => 2,
            'distance_km' => 1,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'status' => 'COMPLETED',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $parentA['student']->id,
            'sort_order' => 1,
            'status' => 'BOARDED',
        ]);
        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $parentB['student']->id,
            'sort_order' => 2,
            'status' => 'BOARDED',
        ]);

        Sanctum::actingAs($parentA['parent']);
        $this->postJson('/api/trips/'.$trip->id.'/rate-driver', ['rating' => 5])->assertOk();

        Sanctum::actingAs($parentB['parent']);
        $this->postJson('/api/trips/'.$trip->id.'/rate-driver', ['rating' => 3])->assertOk()
            ->assertJsonPath('data.driver_rating_avg', 4)
            ->assertJsonPath('data.driver_rating_count', 2);

        $driverUser->refresh();
        $this->assertSame(4.0, (float) $driverUser->rate);
        $this->assertSame(2, (int) $driverUser->votes);
    }

    /**
     * @return array{
     *     parent: User,
     *     student: Student,
     *     driver: Driver,
     *     trip: TripHistory
     * }
     */
    private function seedCompletedTrip(string $status = 'COMPLETED'): array
    {
        $school = $this->makeSchool('Trip Rating School');
        $bundle = $this->makeParentWithStudent($school, '7300000999', '7400000999');

        $driverUser = User::factory()->create(['phone' => '9647909000999', 'rate' => 0, 'votes' => 0]);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'T',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-TR',
            'license_number' => 'LIC-TR',
            'primary_phone' => '7909000999',
            'emergency_phone' => '7909001999',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-TR',
            'route_title' => 'Morning',
            'location' => 'Baghdad',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now()->subHours(2),
            'end_time' => $status === 'COMPLETED' ? now()->subHour() : null,
            'status' => $status,
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $bundle['student']->id,
            'sort_order' => 1,
            'status' => 'BOARDED',
        ]);

        return [
            'parent' => $bundle['parent'],
            'student' => $bundle['student'],
            'driver' => $driver,
            'trip' => $trip,
        ];
    }

    /**
     * @return array{parent: User, student: Student}
     */
    private function makeParentWithStudent(School $school, string $guardianPhone, string $studentPhone): array
    {
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent '.$guardianPhone,
            'phone' => $guardianPhone,
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student '.$studentPhone,
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => $studentPhone,
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create([
            'phone' => '964'.$guardianPhone,
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        return ['parent' => $parent, 'student' => $student];
    }

    private function makeSchool(string $nameEn): School
    {
        return School::query()->create([
            'name_en' => $nameEn,
            'name_ar' => 'مدرسة',
            'province' => 'Baghdad',
            'district' => 'Karrada',
            'address' => 'Street 1',
            'status' => 'active',
        ]);
    }
}
