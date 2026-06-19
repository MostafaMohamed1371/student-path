<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDriverDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_driver_removes_their_trips(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 35,
            'id_card_number' => 'DRV-DEL-1',
            'license_number' => 'LIC-DEL-1',
            'primary_phone' => '7900555200',
            'emergency_phone' => '7900555201',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $otherDriver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'Other',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'Y',
            'age' => 40,
            'id_card_number' => 'DRV-DEL-2',
            'license_number' => 'LIC-DEL-2',
            'primary_phone' => '7900555300',
            'emergency_phone' => '7900555301',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $templateTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'BUS-1',
            'trip_type' => 'MORNING_PICKUP',
            'route_title' => 'Morning route',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addDay(),
            'status' => 'PRESENT',
            'is_recurring_template' => true,
        ]);

        $spawnedTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'BUS-1',
            'trip_type' => 'MORNING_PICKUP',
            'route_title' => 'Morning route',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addDays(2),
            'status' => 'PRESENT',
            'recurring_template_id' => $templateTrip->id,
        ]);

        $otherTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $otherDriver->id,
            'bus_number' => 'BUS-2',
            'trip_type' => 'MORNING_PICKUP',
            'route_title' => 'Other route',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addDay(),
            'status' => 'PRESENT',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->delete(route('dashboard.drivers.destroy', $driver))
            ->assertRedirect(route('dashboard.drivers.index'));

        $this->assertDatabaseMissing('drivers', ['id' => $driver->id]);
        $this->assertDatabaseMissing('trip_histories', ['id' => $templateTrip->id]);
        $this->assertDatabaseMissing('trip_histories', ['id' => $spawnedTrip->id]);
        $this->assertDatabaseHas('trip_histories', ['id' => $otherTrip->id]);
    }
}
