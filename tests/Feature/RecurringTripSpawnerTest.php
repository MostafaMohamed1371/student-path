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
use App\Services\Trips\RecurringTripSpawner;
use App\Support\SchoolWorkDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringTripSpawnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_students_registers_recurring_template(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00', 'UTC'));

        [$school, $driver, $student, $staff, $trip] = $this->seedAssignableTrip();

        $school->update(['work_days' => ['monday']]);

        $this->actingAs($staff);

        $this->post(route('dashboard.trips.assign_students.store'), [
            'trip_ids' => [$trip->id],
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $trip->refresh();
        $this->assertTrue($trip->is_recurring_template);

        $spawned = TripHistory::query()
            ->where('recurring_template_id', $trip->id)
            ->whereDate('start_time', '2026-06-15')
            ->first();

        $this->assertInstanceOf(TripHistory::class, $spawned);
        $this->assertSame((int) $driver->id, (int) $spawned->driver_id);
        $this->assertSame('Route A', $spawned->route_title);
        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $spawned->id,
            'student_id' => $student->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_assigning_students_to_pickup_also_assigns_paired_return_and_spawns_both(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00', 'UTC'));

        [$school, $driver, $student, $staff, $pickupTrip] = $this->seedAssignableTrip();

        $school->update([
            'work_days' => ['monday'],
            'latitude' => 33.32,
            'longitude' => 44.37,
            'address' => 'School Street',
            'work_time_to' => '14:00',
        ]);

        $pickupTrip->update([
            'end_time' => Carbon::parse('2026-06-08 07:45:00'),
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_RETURN->value,
            'bus_number' => '101',
            'route_title' => 'Route A — Morning return',
            'location' => 'School to home',
            'students_count' => 0,
            'distance_km' => 4,
            'start_time' => Carbon::parse('2026-06-08 14:00:00'),
            'end_time' => Carbon::parse('2026-06-08 14:30:00'),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $this->actingAs($staff);

        $this->post(route('dashboard.trips.assign_students.store'), [
            'trip_ids' => [$pickupTrip->id],
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $pickupTrip->refresh();
        $this->assertTrue($pickupTrip->is_recurring_template);
        $this->assertSame(1, (int) $pickupTrip->students_count);

        $returnTrip = TripHistory::query()
            ->where('driver_id', $driver->id)
            ->where('trip_type', TripType::MORNING_RETURN->value)
            ->whereDate('start_time', '2026-06-08')
            ->first();

        $this->assertInstanceOf(TripHistory::class, $returnTrip);
        $this->assertTrue($returnTrip->is_recurring_template);
        $this->assertSame(1, (int) $returnTrip->students_count);
        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $returnTrip->id,
            'student_id' => $student->id,
        ]);

        $spawnedPickup = TripHistory::query()
            ->where('recurring_template_id', $pickupTrip->id)
            ->whereDate('start_time', '2026-06-15')
            ->first();
        $spawnedReturn = TripHistory::query()
            ->where('recurring_template_id', $returnTrip->id)
            ->whereDate('start_time', '2026-06-15')
            ->first();

        $this->assertInstanceOf(TripHistory::class, $spawnedPickup);
        $this->assertInstanceOf(TripHistory::class, $spawnedReturn);
        $this->assertSame('2026-06-15', $spawnedPickup->start_time->toDateString());
        $this->assertSame('2026-06-15', $spawnedReturn->start_time->toDateString());
        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $spawnedPickup->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $spawnedReturn->id,
            'student_id' => $student->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_spawn_command_creates_trip_on_school_work_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 06:00:00', 'UTC')); // Monday

        [$school, $driver, $student] = $this->seedSchoolDriverStudent();

        $school->update([
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'sunday'],
        ]);

        $template = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '101',
            'route_title' => 'Route A',
            'location' => 'Home to school',
            'students_count' => 1,
            'distance_km' => 4,
            'start_time' => Carbon::parse('2026-06-08 07:15:00'),
            'end_time' => Carbon::parse('2026-06-08 07:45:00'),
            'status' => 'ACTIVE',
            'is_recurring_template' => true,
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $template->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        $this->artisan('trips:spawn-recurring', ['--date' => '2026-06-15'])
            ->assertSuccessful();

        $this->assertDatabaseHas('trip_histories', [
            'recurring_template_id' => $template->id,
            'driver_id' => $driver->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_spawner_skips_days_when_school_is_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00', 'UTC'));

        [$school, $driver, $student] = $this->seedSchoolDriverStudent();
        $school->update(['work_days' => ['monday']]);

        $template = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '101',
            'route_title' => 'Route A',
            'location' => 'Home to school',
            'students_count' => 1,
            'distance_km' => 4,
            'start_time' => now()->setTime(7, 15),
            'end_time' => now()->setTime(7, 45),
            'status' => 'ACTIVE',
            'is_recurring_template' => true,
            'students_preview' => [],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $template->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        $spawner = app(RecurringTripSpawner::class);

        $this->assertNull($spawner->spawnTemplateForDay($template, Carbon::parse('2026-06-09 08:00:00', 'UTC')));
        $this->assertSame(0, $spawner->spawnAllForDay(Carbon::parse('2026-06-09 08:00:00', 'UTC')));

        Carbon::setTestNow();
    }

    /**
     * @return array{0: School, 1: Driver, 2: Student, 3: User}
     */
    private function seedSchoolDriverStudent(): array
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Recurring School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
            'work_days' => SchoolWorkDay::keys(),
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Recurring',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'REC-DRV',
            'license_number' => 'REC-LIC',
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
            'number' => '101',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Recurring Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400555500',
            'guardian_name' => 'Guardian',
            'guardian_primary_phone' => '7300555500',
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);

        return [$school, $driver, $student, $staff];
    }

    /**
     * @return array{0: School, 1: Driver, 2: Student, 3: User, 4: TripHistory}
     */
    private function seedAssignableTrip(): array
    {
        [$school, $driver, $student, $staff] = $this->seedSchoolDriverStudent();

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000991',
            'status' => 'active',
        ]);

        $student->update([
            'guardian_id' => $guardian->id,
            'latitude' => 33.314,
            'longitude' => 44.365,
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
            'bus_number' => '101',
            'route_title' => 'Route A',
            'location' => 'Home to school',
            'students_count' => 0,
            'distance_km' => 4,
            'start_time' => now()->setTime(7, 15),
            'end_time' => now()->setTime(7, 45),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        return [$school, $driver, $student, $staff, $trip];
    }
}
