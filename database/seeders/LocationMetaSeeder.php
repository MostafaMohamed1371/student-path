<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\District;
use App\Models\Grade;
use Illuminate\Database\Seeder;

class LocationMetaSeeder extends Seeder
{
    public function run(): void
    {
        if (District::query()->exists()) {
            return;
        }

        $baghdad = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        foreach (['Karkh', 'Rusafa', 'Sadr City', 'Mansour'] as $i => $name) {
            Area::query()->create([
                'district_id' => $baghdad->id,
                'name' => $name,
                'sort_order' => $i,
            ]);
        }

        $basra = District::query()->create(['name' => 'Basra', 'sort_order' => 2]);
        Area::query()->create(['district_id' => $basra->id, 'name' => 'Central Basra', 'sort_order' => 0]);

        for ($g = 1; $g <= 12; $g++) {
            Grade::query()->create([
                'name' => 'Grade '.$g,
                'sort_order' => $g,
            ]);
        }
    }
}
