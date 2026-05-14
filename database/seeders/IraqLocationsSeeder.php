<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\District;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds {@see District}, {@see Area}, and {@see Neighborhood} from {@see database/data/iraq_location_catalog.php}
 * for parent APIs such as {@code GET /api/locations/districts}, {@code /api/locations/iraq}, etc.
 *
 * Replaces all existing rows in those tables (does not touch grades or other models).
 */
class IraqLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/iraq_location_catalog.php');
        if (! is_readable($path)) {
            $this->command?->error('Missing catalog file: '.$path);

            return;
        }

        /** @var list<array{name: string, sort_order: int, areas: list<array{name: string, sort_order: int, neighborhoods: list<array{name: string, latitude: float, longitude: float}>}>}> $tree */
        $tree = require $path;

        Schema::disableForeignKeyConstraints();
        try {
            Neighborhood::query()->delete();
            Area::query()->delete();
            District::query()->delete();

            foreach ($tree as $districtRow) {
                $district = District::query()->create([
                    'name' => $districtRow['name'],
                    'sort_order' => $districtRow['sort_order'],
                ]);

                foreach ($districtRow['areas'] as $areaRow) {
                    $area = Area::query()->create([
                        'district_id' => $district->id,
                        'name' => $areaRow['name'],
                        'sort_order' => $areaRow['sort_order'],
                    ]);

                    foreach ($areaRow['neighborhoods'] as $ni => $nRow) {
                        Neighborhood::query()->create([
                            'area_id' => $area->id,
                            'name' => $nRow['name'],
                            'sort_order' => $ni,
                            'latitude' => $nRow['latitude'],
                            'longitude' => $nRow['longitude'],
                        ]);
                    }
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info('Iraq location catalog seeded: '
            .District::query()->count().' districts, '
            .Area::query()->count().' areas, '
            .Neighborhood::query()->count().' neighborhoods.');
    }
}
