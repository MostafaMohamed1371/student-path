<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

/**
 * Sample Iraqi neighborhoods under seeded {@see Area} rows (coordinates approximate).
 */
class NeighborhoodSeeder extends Seeder
{
    public function run(): void
    {
        if (Neighborhood::query()->exists()) {
            return;
        }

        /** @var array<string, list<array{name: string, latitude: float, longitude: float}>> $byAreaName */
        $byAreaName = [
            'Karkh' => [
                ['name' => 'Al-Jadriya', 'latitude' => 33.2889, 'longitude' => 44.3665],
                ['name' => 'Karrada', 'latitude' => 33.3152, 'longitude' => 44.3661],
            ],
            'Rusafa' => [
                ['name' => 'Bab Al-Moatham', 'latitude' => 33.3406, 'longitude' => 44.4009],
                ['name' => 'Al-Fadhilia', 'latitude' => 33.3521, 'longitude' => 44.4212],
            ],
            'Sadr City' => [
                ['name' => 'Sector 83', 'latitude' => 33.3844, 'longitude' => 44.4578],
            ],
            'Mansour' => [
                ['name' => 'Al-Mansour district center', 'latitude' => 33.3044, 'longitude' => 44.3412],
                ['name' => 'Al-Amiriyah', 'latitude' => 33.2867, 'longitude' => 44.2489],
            ],
            'Central Basra' => [
                ['name' => 'Ashar', 'latitude' => 30.5085, 'longitude' => 47.7835],
                ['name' => 'Al-Jumhouriya', 'latitude' => 30.4951, 'longitude' => 47.8099],
            ],
        ];

        foreach (Area::query()->orderBy('district_id')->orderBy('sort_order')->get() as $area) {
            $blocks = $byAreaName[$area->name] ?? [
                ['name' => $area->name.' — central', 'latitude' => 33.3152, 'longitude' => 44.3661],
            ];
            foreach ($blocks as $i => $row) {
                Neighborhood::query()->create([
                    'area_id' => $area->id,
                    'name' => $row['name'],
                    'sort_order' => $i,
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                ]);
            }
        }
    }
}
