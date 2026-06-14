<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\TripDriverReplacement;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Trips\RecurringTripSpawner;
use App\Support\SchoolWorkDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTripReplacementDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_shows_replacement_section_for_pinned_recurring_template(): void
    {
        [$school, $primaryDriver, $replacementDriver, $admin, $trip] = $this->seedRecurringTemplateTrip();

        $this->actingAs($admin)
            ->get(route('dashboard.trips.edit', $trip))
            ->assertOk()
            ->assertSee(__('dashboard.trip_replacement_drivers_title'), false);
    }

    public function test_update_syncs_replacement_driver_and_applies_to_spawned_trip(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00', 'UTC'));

        [$school, $primaryDriver, $replacementDriver, $admin, $trip] = $this->seedRecurringTemplateTrip();
        $school->update(['work_days' => ['monday']]);

        $trip->update(['is_recurring_template' => true]);
        app(RecurringTripSpawner::class)->spawnAheadForTemplate($trip->fresh());

        $replacementDate = '2026-06-15';

        $this->actingAs($admin)
            ->put(route('dashboard.trips.update', $trip), $this->tripUpdatePayload($trip, [
                'replacement_dates' => [$replacementDate],
                'replacement_driver_ids' => [(int) $replacementDriver->id],
            ]))
            ->assertRedirect(route('dashboard.trips.index'));

        $this->assertDatabaseHas('trip_driver_replacements', [
            'template_trip_id' => $trip->id,
            'replacement_driver_id' => $replacementDriver->id,
        ]);

        $this->assertSame(
            1,
            TripDriverReplacement::query()
                ->where('template_trip_id', $trip->id)
                ->whereDate('service_date', $replacementDate)
                ->count(),
        );

        $spawnedPickup = TripHistory::query()
            ->where('recurring_template_id', $trip->id)
            ->whereDate('start_time', $replacementDate)
            ->firstOrFail();

        $this->assertSame((int) $replacementDriver->id, (int) $spawnedPickup->driver_id);

        Carbon::setTestNow();
    }

    public function test_spawner_uses_replacement_driver_for_new_spawn_on_configured_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00', 'UTC'));

        [$school, $primaryDriver, $replacementDriver, $admin, $trip] = $this->seedRecurringTemplateTrip();
        $school->update(['work_days' => ['monday']]);

        $trip->update(['is_recurring_template' => true]);

        TripDriverReplacement::query()->create([
            'template_trip_id' => $trip->id,
            'service_date' => '2026-06-15',
            'replacement_driver_id' => $replacementDriver->id,
        ]);

        app(RecurringTripSpawner::class)->spawnAheadForTemplate($trip->fresh());

        $spawnedPickup = TripHistory::query()
            ->where('recurring_template_id', $trip->id)
            ->whereDate('start_time', '2026-06-15')
            ->firstOrFail();

        $this->assertSame((int) $replacementDriver->id, (int) $spawnedPickup->driver_id);

        Carbon::setTestNow();
    }

    /**
     * @return array{0: School, 1: Driver, 2: Driver, 3: User, 4: TripHistory}
     */
    private function seedRecurringTemplateTrip(): array
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Replacement School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
            'work_days' => SchoolWorkDay::keys(),
        ]);

        $primaryUser = User::factory()->create();
        $primaryDriver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $primaryUser->id,
            'first_name' => 'Primary',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'PRI-DRV',
            'license_number' => 'PRI-LIC',
            'primary_phone' => '7900111100',
            'emergency_phone' => '7900111101',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $replacementUser = User::factory()->create();
        $replacementDriver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $replacementUser->id,
            'first_name' => 'Replacement',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'Two',
            'age' => 36,
            'id_card_number' => 'REP-DRV',
            'license_number' => 'REP-LIC',
            'primary_phone' => '7900222200',
            'emergency_phone' => '7900222201',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $primaryUser->id,
            'driver_id' => $primaryDriver->id,
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

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $primaryDriver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '101',
            'route_title' => 'Route A',
            'location' => 'Home to school',
            'students_count' => 0,
            'distance_km' => 4,
            'start_time' => Carbon::parse('2026-06-08 07:15:00'),
            'end_time' => Carbon::parse('2026-06-08 07:45:00'),
            'status' => 'PRESENT',
            'auto_schedule_work_days' => true,
            'is_recurring_template' => true,
            'students_preview' => [],
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        return [$school, $primaryDriver, $replacementDriver, $admin, $trip];
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
            'students_count' => $trip->students_count,
            'distance_km' => $trip->distance_km,
            'start_time' => optional($trip->start_time)->format('Y-m-d\TH:i'),
            'end_time' => optional($trip->end_time)->format('Y-m-d\TH:i'),
            'status' => $trip->status,
            'auto_schedule_work_days' => (bool) $trip->auto_schedule_work_days,
        ], $overrides);
    }
}
