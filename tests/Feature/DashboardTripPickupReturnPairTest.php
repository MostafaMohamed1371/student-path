<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTripPickupReturnPairTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_pickup_trip_auto_creates_return_trip_at_school_end_time(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Pair School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'PAIR-DRV',
            'license_number' => 'PAIR-LIC',
            'primary_phone' => '7900111001',
            'emergency_phone' => '7900111002',
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
            'number' => 'PAIR-BUS',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $start = now()->startOfDay()->setTime(7, 0);
        $end = now()->startOfDay()->setTime(7, 45);

        $this->post(route('dashboard.trips.store'), [
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'PAIR-BUS',
            'start_address' => 'Depot',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $end->format('Y-m-d\TH:i'),
            'status' => 'PRESENT',
        ])->assertRedirect();

        $pickup = TripHistory::query()
            ->where('trip_type', TripType::MORNING_PICKUP->value)
            ->firstOrFail();

        $return = TripHistory::query()
            ->where('trip_type', TripType::MORNING_RETURN->value)
            ->firstOrFail();

        $this->assertSame((int) $driver->id, (int) $return->driver_id);
        $this->assertTrue($return->start_time?->equalTo($end));
        $this->assertTrue($return->end_time?->equalTo($end->copy()->addMinutes(45)));
        $this->assertSame('School Ave', $return->start_address);
        $this->assertNotSame((int) $pickup->id, (int) $return->id);
    }

    public function test_pickup_trip_requires_end_time_on_create(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'No End School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'PAIR-DRV-2',
            'license_number' => 'PAIR-LIC-2',
            'primary_phone' => '7900111011',
            'emergency_phone' => '7900111012',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('dashboard.trips.store'), [
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'BUS-2',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->format('Y-m-d\TH:i'),
            'status' => 'PRESENT',
        ])->assertSessionHasErrors('end_time');

        $this->assertSame(0, TripHistory::query()->count());
    }
}
