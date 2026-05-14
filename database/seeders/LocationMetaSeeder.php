<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

/**
 * Grade catalog for {@code GET /api/meta/grades} and related flows.
 *
 * Location hierarchy (districts / areas / neighborhoods) is seeded by {@see IraqLocationsSeeder}.
 */
class LocationMetaSeeder extends Seeder
{
    public function run(): void
    {
        if (Grade::query()->exists()) {
            return;
        }

        for ($g = 1; $g <= 12; $g++) {
            Grade::query()->create([
                'name' => 'Grade '.$g,
                'sort_order' => $g,
            ]);
        }
    }
}
