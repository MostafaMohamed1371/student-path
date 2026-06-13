<?php

namespace Tests\Unit;

use App\Enums\TripType;
use App\Models\Driver;
use App\Models\School;
use App\Models\TransportRoute;
use App\Models\User;
use App\Services\Trips\TripTransportRouteApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTransportRouteApplierReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_trip_form_payload_uses_school_to_pickup_start_path(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Street',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'D',
            'age' => 30,
            'id_card_number' => 'IDC-R',
            'license_number' => 'LIC-R',
            'primary_phone' => '7700000101',
            'emergency_phone' => '7700000102',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $pickupRoute = TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning line',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Driver depot',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'status' => 'active',
        ]);

        $applier = app(TripTransportRouteApplier::class);

        $payload = $applier->returnTripFormPayload([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_RETURN->value,
        ], TripType::MORNING_RETURN->value);

        $this->assertNotNull($payload);
        $this->assertSame(33.32, $payload['route_start_latitude']);
        $this->assertSame(44.37, $payload['route_start_longitude']);
        $this->assertSame(33.31, $payload['route_end_latitude']);
        $this->assertSame(44.36, $payload['route_end_longitude']);
        $this->assertSame('School Street', $payload['start_address']);
        $this->assertSame('Driver depot', $payload['end_address']);
        $this->assertStringContainsString('Morning return', (string) $payload['route_title']);
        $this->assertStringContainsString('School Street', (string) $payload['location']);
        $this->assertStringContainsString('Driver depot', (string) $payload['location']);
    }

    public function test_apply_return_trip_path_attributes_sets_school_as_trip_start(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Street',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'D',
            'age' => 30,
            'id_card_number' => 'IDC-R2',
            'license_number' => 'LIC-R2',
            'primary_phone' => '7700000103',
            'emergency_phone' => '7700000104',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning line',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Driver depot',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'status' => 'active',
        ]);

        $applier = app(TripTransportRouteApplier::class);

        $attributes = $applier->applyReturnTripPathAttributes([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_RETURN->value,
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'start_address' => 'Driver depot',
        ], $school);

        $this->assertSame(33.32, $attributes['start_latitude']);
        $this->assertSame(44.37, $attributes['start_longitude']);
        $this->assertSame('School Street', $attributes['start_address']);
        $this->assertGreaterThan(0, (float) $attributes['distance_km']);
    }
}
