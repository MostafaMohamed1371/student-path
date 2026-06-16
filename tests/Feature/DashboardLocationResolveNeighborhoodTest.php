<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IraqLocationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLocationResolveNeighborhoodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IraqLocationsSeeder::class);
    }

    public function test_resolve_neighborhood_returns_nearest_sub_district_for_map_click(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'is_admin' => false,
        ]);

        $neighborhood = Neighborhood::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->firstOrFail();

        $response = $this->actingAs($user)->getJson(route('dashboard.locations.resolve_neighborhood', [
            'latitude' => $neighborhood->latitude,
            'longitude' => $neighborhood->longitude,
        ]));

        $response->assertOk()
            ->assertJsonPath('neighborhood.id', $neighborhood->id)
            ->assertJsonPath('neighborhood.area_id', $neighborhood->area_id);
    }

    public function test_neighborhoods_endpoint_can_include_coordinates_for_map_markers(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'is_admin' => false,
        ]);

        $district = District::query()->firstOrFail();
        $area = Area::query()->where('district_id', $district->id)->firstOrFail();

        $response = $this->actingAs($user)->getJson(route('dashboard.locations.neighborhoods', [
            'district_id' => $district->id,
            'area_id' => $area->id,
            'with_coordinates' => 1,
        ]));

        $response->assertOk();
        $first = $response->json('neighborhoods.0');
        $this->assertNotNull($first);
        $this->assertArrayHasKey('latitude', $first);
        $this->assertArrayHasKey('longitude', $first);
        $this->assertArrayHasKey('district_id', $first);
    }
}
