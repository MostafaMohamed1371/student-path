<?php

namespace Tests\Unit;

use App\Enums\TripType;
use App\Models\School;
use App\Services\Trips\PickupReturnTripPairPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickupReturnTripPairPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_return_trip_starting_at_pickup_end_time(): void
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

        $planner = new PickupReturnTripPairPlanner;

        $attributes = $planner->returnTripAttributesFromPickup([
            'school_id' => $school->id,
            'driver_id' => 5,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-1',
            'start_address' => 'Depot',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'distance_km' => 4.5,
            'start_time' => '2026-06-10 07:00:00',
            'end_time' => '2026-06-10 07:45:00',
            'status' => 'PRESENT',
        ], $school);

        $this->assertNotNull($attributes);
        $this->assertSame(TripType::MORNING_RETURN->value, $attributes['trip_type']);
        $this->assertSame('2026-06-10 07:45:00', $attributes['start_time']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-10 08:30:00', $attributes['end_time']->format('Y-m-d H:i:s'));
        $this->assertSame(33.32, $attributes['start_latitude']);
        $this->assertSame(44.37, $attributes['start_longitude']);
        $this->assertSame('School Street', $attributes['start_address']);
        $this->assertStringContainsString('Morning return', (string) $attributes['route_title']);
    }

    public function test_returns_null_for_non_pickup_trip_types(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Street',
            'status' => 'active',
        ]);

        $planner = new PickupReturnTripPairPlanner;

        $attributes = $planner->returnTripAttributesFromPickup([
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_RETURN->value,
            'start_time' => '2026-06-10 07:00:00',
            'end_time' => '2026-06-10 07:45:00',
        ], $school);

        $this->assertNull($attributes);
    }
}
