<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDriverFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_options_returns_all_buses_for_selected_school(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School B',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        $busA = Bus::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'Bus A',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'A-1',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'school_id' => $schoolB->id,
            'name' => 'Bus B',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.drivers.form_options', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertJsonCount(1, 'buses')
            ->assertJsonPath('buses.0.id', $busA->id)
            ->assertJsonPath('buses.0.label', 'A-1 — Bus A');
    }

    public function test_school_staff_can_load_form_options_for_their_school(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Staff School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);

        $bus = Bus::query()->create([
            'school_id' => $school->id,
            'name' => 'Morning Bus',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'S-1',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $this->actingAs($staff)
            ->getJson(route('dashboard.drivers.form_options', ['school_id' => $school->id]))
            ->assertOk()
            ->assertJsonCount(1, 'buses')
            ->assertJsonPath('buses.0.id', $bus->id);
    }

    public function test_form_options_includes_bus_already_assigned_to_another_driver(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        $existingDriver = \App\Models\Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Old',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-OLD',
            'license_number' => 'LIC-OLD',
            'primary_phone' => '7770000999',
            'emergency_phone' => '7770001999',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $bus = Bus::query()->create([
            'school_id' => $school->id,
            'driver_id' => $existingDriver->id,
            'user_id' => $user->id,
            'name' => 'Taken Bus',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'T-1',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.drivers.form_options', ['school_id' => $school->id]))
            ->assertOk()
            ->assertJsonCount(1, 'buses')
            ->assertJsonPath('buses.0.id', $bus->id)
            ->assertJsonPath('buses.0.label', 'T-1 — Taken Bus (Old Driver One)');
    }
}
