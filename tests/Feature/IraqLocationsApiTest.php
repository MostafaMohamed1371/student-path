<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\IraqLocationsSeeder;
use Database\Seeders\LocationMetaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IraqLocationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IraqLocationsSeeder::class);
        $this->seed(LocationMetaSeeder::class);
    }

    public function test_locations_iraq_returns_nested_districts_areas_neighborhoods(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/locations/iraq')->assertOk();
        $response->assertJsonPath('distance_reference.source', 'iraq_default');
        $res = $response->json('data');
        $this->assertNotEmpty($res);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertArrayHasKey('name', $res[0]);
        $this->assertNotEmpty($res[0]['areas']);
        $this->assertArrayHasKey('neighborhoods', $res[0]['areas'][0]);
        $this->assertNotEmpty($res[0]['areas'][0]['neighborhoods']);
        $n = $res[0]['areas'][0]['neighborhoods'][0];
        $this->assertArrayHasKey('id', $n);
        $this->assertArrayHasKey('name', $n);
        $this->assertArrayHasKey('latitude', $n);
        $this->assertArrayHasKey('longitude', $n);
        $this->assertArrayHasKey('distance_km', $n);
        $this->assertNotNull($n['distance_km']);
        $this->assertIsNumeric($n['distance_km']);
    }

    public function test_locations_iraq_includes_distance_km_when_reference_coordinates_sent(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/locations/iraq?latitude=33.3152&longitude=44.3661')->assertOk();
        $response->assertJsonPath('distance_reference.source', 'request');
        $res = $response->json('data');

        $found = false;
        foreach ($res as $district) {
            foreach ($district['areas'] as $area) {
                foreach ($area['neighborhoods'] as $n) {
                    if ($n['distance_km'] !== null) {
                        $this->assertIsNumeric($n['distance_km']);
                        $found = true;
                    }
                }
            }
        }
        $this->assertTrue($found);
    }

    public function test_locations_iraq_max_radius_km_filters_distant_neighborhoods(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $wide = $this->getJson('/api/locations/iraq?latitude=33.3152&longitude=44.3661&max_radius_km=500')
            ->assertOk()
            ->json('data');
        $wideCount = $this->countNeighborhoods($wide);

        $narrow = $this->getJson('/api/locations/iraq?latitude=33.3152&longitude=44.3661&max_radius_km=0.5')
            ->assertOk()
            ->json('data');
        $narrowCount = $this->countNeighborhoods($narrow);

        $this->assertGreaterThan($narrowCount, $wideCount);
    }

    public function test_locations_iraq_rejects_latitude_without_longitude(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/locations/iraq?latitude=33.0')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     */
    private function countNeighborhoods(array $tree): int
    {
        $c = 0;
        foreach ($tree as $district) {
            foreach ($district['areas'] as $area) {
                $c += count($area['neighborhoods']);
            }
        }

        return $c;
    }
}
