<?php

namespace Tests\Unit;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\TransportRoute;
use App\Models\User;
use App\Services\Trips\TripTransportRouteApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTransportRouteApplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_route_fills_trip_title_location_and_distance(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'School Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-APP',
            'license_number' => 'LIC-APP',
            'primary_phone' => '7770000600',
            'emergency_phone' => '7770000601',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        Bus::query()->create([
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Van',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'white',
            'capacity' => 10,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route A',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot Start',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $applier = app(TripTransportRouteApplier::class);

        $result = $applier->applyRouteToTripAttributes([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'route_title' => '',
            'location' => '',
            'distance_km' => 0,
        ]);

        $this->assertSame('Route A — Depot Start', $result['route_title']);
        $this->assertStringContainsString('Depot Start', (string) $result['location']);
        $this->assertStringContainsString('School Campus', (string) $result['location']);
        $this->assertGreaterThan(0, (float) $result['distance_km']);

        $payload = $applier->driverRouteFormPayload(
            TransportRoute::query()->with('school')->where('driver_id', $driver->id)->first(),
        );
        $this->assertNotNull($payload['distance_km']);
        $this->assertSame($payload['distance_km'], $applier->routeDistanceKm(
            TransportRoute::query()->with('school')->where('driver_id', $driver->id)->first(),
        ));
    }
}
