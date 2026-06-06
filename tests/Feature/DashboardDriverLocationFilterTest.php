<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Driver;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDriverLocationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_drivers_index_can_be_filtered_by_iraq_location(): void
    {
        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $district = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Rusafa', 'sort_order' => 0]);
        $subDistrict = Neighborhood::query()->create([
            'area_id' => $district->id,
            'name' => 'Al-Karrada',
            'sort_order' => 0,
        ]);
        $otherArea = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 1]);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Location School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $karradaDriver = Driver::query()->create([
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $district->id,
            'first_name' => 'Sam',
            'father_name' => 'Karrada',
            'grandfather_name' => 'X',
            'last_name' => 'Driver',
            'age' => 30,
            'id_card_number' => 'KARRADA-1',
            'license_number' => 'LIC-KARRADA-1',
            'primary_phone' => '7900111001',
            'emergency_phone' => '7900112001',
            'residential_address' => 'Address',
            'status' => 'active',
        ]);
        $karradaDriver->neighborhoods()->attach($subDistrict->id);

        Driver::query()->create([
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $otherArea->id,
            'first_name' => 'Other',
            'father_name' => 'Karkh',
            'grandfather_name' => 'X',
            'last_name' => 'Driver',
            'age' => 30,
            'id_card_number' => 'KARKH-1',
            'license_number' => 'LIC-KARKH-1',
            'primary_phone' => '7900111002',
            'emergency_phone' => '7900112002',
            'residential_address' => 'Address',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.drivers.index'))
            ->assertOk()
            ->assertSee(__('dashboard.governorate'), false)
            ->assertSee(__('dashboard.iraq_district'), false)
            ->assertSee(__('dashboard.iraq_sub_district'), false);

        $this->get(route('dashboard.drivers.index', [
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $district->id,
            'neighborhood_id' => $subDistrict->id,
        ]))
            ->assertOk()
            ->assertSee('Sam', false)
            ->assertSee('Karrada', false)
            ->assertDontSee('Other', false);
    }
}
