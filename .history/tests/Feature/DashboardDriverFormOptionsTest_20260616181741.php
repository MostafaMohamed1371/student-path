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

    public function test_form_options_returns_unassigned_buses_for_selected_school(): void
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
}
