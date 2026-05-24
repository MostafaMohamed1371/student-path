<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Trips\TripLocationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTripLiveTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['trips.location_store' => 'cache']);
    }

    public function test_dashboard_trip_show_includes_live_map_for_staff(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.3,
            'longitude' => 44.3,
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC',
            'license_number' => 'LIC',
            'primary_phone' => '1',
            'emergency_phone' => '2',
            'residential_address' => 'A',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B-1',
            'route_title' => 'Route',
            'students_count' => 0,
            'start_time' => now(),
            'driver_started_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.trips.show', $trip))
            ->assertOk()
            ->assertSee('trip_live_map', false)
            ->assertSee(__('dashboard.trip_live_tracking_title'), false);
    }

    public function test_dashboard_tracking_json_returns_location(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
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
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC',
            'license_number' => 'LIC',
            'primary_phone' => '1',
            'emergency_phone' => '2',
            'residential_address' => 'A',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B-1',
            'route_title' => 'Route',
            'students_count' => 0,
            'start_time' => now(),
            'driver_started_at' => now(),
            'status' => 'ACTIVE',
        ]);

        app(TripLocationTrackingService::class)->updateDriverLocation($driver, $trip, [
            'latitude' => 33.31,
            'longitude' => 44.36,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
            'school_id' => $school->id,
        ]);
        $this->actingAs($staff);

        $this->getJson(route('dashboard.trips.tracking', $trip))
            ->assertOk()
            ->assertJsonPath('data.tracking_active', true)
            ->assertJsonPath('data.location.latitude', 33.31);
    }
}
