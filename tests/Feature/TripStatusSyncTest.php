<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Trips\DriverTripModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_is_auto_cancelled_when_start_window_passes_without_driver_start(): void
    {
        config([
            'trips.driver_start_early_minutes' => 10,
            'trips.driver_start_late_minutes' => 10,
        ]);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Sync',
            'age' => 30,
            'id_card_number' => 'IDC-SYNC-1',
            'license_number' => 'LIC-SYNC-1',
            'primary_phone' => '7770000881',
            'emergency_phone' => '7770001881',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $start = now()->setTime(7, 0, 0);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B-1',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'PRESENT',
            'students_preview' => [],
        ]);

        $this->travelTo($start->copy()->addMinutes(11));

        $service = app(DriverTripModuleService::class);
        $this->assertTrue($service->syncTripStatus($trip));
        $this->assertSame('CANCELLED', $trip->fresh()->status);

        Sanctum::actingAs($driverUser);
        $this->postJson('/api/trips/TRP-'.$trip->id.'/start')
            ->assertStatus(422)
            ->assertJsonPath('msg', 'Cannot start a cancelled trip.');

        $this->travelBack();
    }

    public function test_trip_is_auto_completed_when_scheduled_end_passes_after_driver_start(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Sync',
            'age' => 30,
            'id_card_number' => 'IDC-SYNC-2',
            'license_number' => 'LIC-SYNC-2',
            'primary_phone' => '7770000882',
            'emergency_phone' => '7770001882',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $start = now()->setTime(8, 0, 0);
        $end = $start->copy()->addHour();

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B-1',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => $start,
            'end_time' => $end,
            'driver_started_at' => $start->copy()->addMinutes(5),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $this->travelTo($end->copy()->addMinute());

        $service = app(DriverTripModuleService::class);
        $this->assertTrue($service->syncTripStatus($trip));
        $this->assertSame('COMPLETED', $trip->fresh()->status);

        $this->travelBack();
    }

    public function test_upcoming_trip_is_normalized_to_present_in_database(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Sync',
            'age' => 30,
            'id_card_number' => 'IDC-SYNC-4',
            'license_number' => 'LIC-SYNC-4',
            'primary_phone' => '7770000884',
            'emergency_phone' => '7770001884',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $start = now()->addHour();
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B-1',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $service = app(DriverTripModuleService::class);
        $this->assertTrue($service->syncTripStatus($trip));
        $this->assertSame('PRESENT', $trip->fresh()->status);
    }

    public function test_scheduled_trips_api_reflects_cancelled_status_after_sync(): void
    {
        config([
            'trips.driver_start_early_minutes' => 0,
            'trips.driver_start_late_minutes' => 5,
        ]);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Sync',
            'age' => 30,
            'id_card_number' => 'IDC-SYNC-3',
            'license_number' => 'LIC-SYNC-3',
            'primary_phone' => '7770000883',
            'emergency_phone' => '7770001883',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $start = now()->startOfDay()->addHours(9);
        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B-1',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(1),
            'status' => 'PRESENT',
            'students_preview' => [],
        ]);

        $this->travelTo($start->copy()->addMinutes(6));

        Sanctum::actingAs($driverUser);
        $this->getJson('/api/scheduled-trips')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'cancelled');

        $this->travelBack();
    }
}
