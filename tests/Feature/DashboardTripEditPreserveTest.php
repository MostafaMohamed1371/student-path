<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use App\Services\Trips\RecurringTripSpawner;
use App\Services\Trips\TripRecurringScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ProvidesDashboardIraqLocationFields;
use Tests\TestCase;

class DashboardTripEditPreserveTest extends TestCase
{
    use ProvidesDashboardIraqLocationFields;
    use RefreshDatabase;

    public function test_update_preserves_assigned_student_count_when_note_changes(): void
    {
        [$school, $driver, $student, $admin, $trip] = $this->seedTripWithOneStudent();

        $this->actingAs($admin)
            ->put(route('dashboard.trips.update', $trip), $this->tripUpdatePayload($trip, [
                'note' => 'Updated note only',
                'students_count' => 30,
            ]))
            ->assertRedirect(route('dashboard.trips.index'));

        $trip->refresh();

        $this->assertSame('Updated note only', $trip->note);
        $this->assertSame(1, (int) $trip->students_count);
        $this->assertSame(1, $trip->tripHistoryStudents()->count());
    }

    public function test_update_preserves_route_coordinates_when_only_note_changes(): void
    {
        [$school, $driver, $student, $admin, $trip] = $this->seedTripWithOneStudent();

        $trip->update([
            'start_latitude' => 33.301111,
            'start_longitude' => 44.351111,
            'start_address' => 'Saved start pin',
            'route_title' => 'Pinned route',
            'location' => 'Saved start pin → School',
            'distance_km' => 5.5,
        ]);

        $this->actingAs($admin)
            ->put(route('dashboard.trips.update', $trip), $this->withTripStartIraqLocation($this->tripUpdatePayload($trip->fresh(), [
                'note' => 'Only note changed',
                'start_latitude' => 33.301111,
                'start_longitude' => 44.351111,
                'start_address' => 'Saved start pin',
                'route_title' => 'Pinned route',
                'location' => 'Saved start pin → School',
                'distance_km' => 5.5,
            ])))
            ->assertRedirect(route('dashboard.trips.index'));

        $trip->refresh();

        $this->assertSame('Only note changed', $trip->note);
        $this->assertSame(33.301111, (float) $trip->start_latitude);
        $this->assertSame(44.351111, (float) $trip->start_longitude);
        $this->assertSame('Saved start pin', $trip->start_address);
        $this->assertSame('Pinned route', $trip->route_title);
    }

    public function test_sync_trip_does_not_register_template_without_assigned_students(): void
    {
        [$school, $driver, $student, $admin, $trip] = $this->seedTripWithOneStudent();

        $trip->tripHistoryStudents()->delete();
        $trip->update([
            'students_count' => 30,
            'auto_schedule_work_days' => true,
            'is_recurring_template' => true,
        ]);

        app(TripRecurringScheduleService::class)->syncTrip($trip->fresh(['school', 'tripHistoryStudents']));

        $this->assertFalse((bool) $trip->fresh()->is_recurring_template);
    }

    public function test_spawned_trip_copies_assigned_student_count_not_inflated_template_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00', 'UTC'));

        [$school, $driver, $student, $admin, $trip] = $this->seedTripWithOneStudent();
        $school->update(['work_days' => ['monday']]);

        $trip->update([
            'students_count' => 30,
            'auto_schedule_work_days' => true,
            'is_recurring_template' => true,
            'start_time' => Carbon::parse('2026-06-08 07:15:00'),
        ]);

        app(RecurringTripSpawner::class)->spawnAheadForTemplate($trip->fresh(['school', 'tripHistoryStudents']));

        $spawned = TripHistory::query()
            ->where('recurring_template_id', $trip->id)
            ->whereDate('start_time', '2026-06-15')
            ->firstOrFail();

        $this->assertSame(1, (int) $spawned->students_count);
        $this->assertSame(1, $spawned->tripHistoryStudents()->count());

        Carbon::setTestNow();
    }

    /**
     * @return array{0: School, 1: Driver, 2: Student, 3: User, 4: TripHistory}
     */
    private function seedTripWithOneStudent(): array
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Edit Preserve School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'latitude' => 33.315,
            'longitude' => 44.366,
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7900333300',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Trip Student',
            'gender' => 'male',
            'grade' => 'G1',
            'student_phone' => '7900444400',
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
            'first_name' => 'Driver',
            'father_name' => 'X',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'DRV-EDIT',
            'license_number' => 'LIC-EDIT',
            'primary_phone' => '7900555500',
            'emergency_phone' => '7900555501',
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
            'number' => '30',
            'color' => 'yellow',
            'capacity' => 30,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '30',
            'route_title' => 'Route A',
            'location' => 'Home to school',
            'students_count' => 1,
            'distance_km' => 4,
            'start_time' => Carbon::parse('2026-06-08 07:15:00'),
            'end_time' => Carbon::parse('2026-06-08 07:45:00'),
            'status' => 'PRESENT',
            'auto_schedule_work_days' => false,
            'students_preview' => [],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        return [$school, $driver, $student, $admin, $trip];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tripUpdatePayload(TripHistory $trip, array $overrides = []): array
    {
        return array_merge([
            'school_id' => $trip->school_id,
            'driver_id' => $trip->driver_id,
            'trip_type' => $trip->trip_type,
            'bus_number' => $trip->bus_number,
            'route_title' => $trip->route_title,
            'location' => $trip->location,
            'distance_km' => $trip->distance_km,
            'start_time' => optional($trip->start_time)->format('Y-m-d\TH:i'),
            'end_time' => optional($trip->end_time)->format('Y-m-d\TH:i'),
            'status' => $trip->status,
            'auto_schedule_work_days' => (bool) $trip->auto_schedule_work_days,
        ], $overrides);
    }
}
