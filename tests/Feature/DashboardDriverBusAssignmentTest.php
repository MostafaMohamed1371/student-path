<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDriverBusAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bus_can_be_created_without_driver_and_assigned_on_driver_create(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('dashboard.buses.store'), [
            'school_id' => $school->id,
            'name' => 'Morning Bus',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'BUS-100',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
            'annual_status' => 1,
            'insurance' => 1,
        ])->assertRedirect(route('dashboard.buses.index'));

        $bus = Bus::query()->where('number', 'BUS-100')->firstOrFail();
        $this->assertSame((int) $school->id, (int) $bus->school_id);
        $this->assertNull($bus->driver_id);
        $this->assertNull($bus->user_id);

        $this->post(route('dashboard.drivers.store'), [
            'school_id' => $school->id,
            'bus_id' => $bus->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 35,
            'id_card_number' => 'DRV-BUS-1',
            'license_number' => 'LIC-BUS-1',
            'primary_phone' => '7900555100',
            'emergency_phone' => '7900555101',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.drivers.index'));

        $driver = Driver::query()->where('id_card_number', 'DRV-BUS-1')->firstOrFail();
        $bus->refresh();

        $this->assertSame((int) $driver->id, (int) $bus->driver_id);
        $this->assertSame((int) $driver->user_id, (int) $bus->user_id);
    }
}
