<?php

namespace Tests\Unit;

use App\Models\Area;
use App\Models\District;
use App\Models\Driver;
use App\Models\Neighborhood;
use App\Models\School;
use App\Services\Drivers\DriverIraqLocationFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverIraqLocationFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneCounter = 7900100000;

    public function test_governorate_scope_includes_drivers_in_child_areas_and_neighborhoods(): void
    {
        $gov = District::query()->create(['name' => 'Gov', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $gov->id, 'name' => 'Area', 'sort_order' => 0]);
        $sub = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Sub',
            'sort_order' => 0,
        ]);
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $byGov = $this->createDriver($school->id, 'By Gov', $gov->id, null);
        $byArea = $this->createDriver($school->id, 'By Area', null, $area->id);
        $bySub = $this->createDriver($school->id, 'By Sub', $gov->id, $area->id);
        $bySub->neighborhoods()->attach($sub->id);

        $ids = Driver::query()
            ->tap(fn ($q) => app(DriverIraqLocationFilter::class)->apply($q, $gov->id, 0, 0))
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$byGov->id, $byArea->id, $bySub->id],
            $ids,
        );
    }

    public function test_area_scope_excludes_drivers_in_sibling_area(): void
    {
        $gov = District::query()->create(['name' => 'Gov', 'sort_order' => 1]);
        $areaA = Area::query()->create(['district_id' => $gov->id, 'name' => 'A', 'sort_order' => 0]);
        $areaB = Area::query()->create(['district_id' => $gov->id, 'name' => 'B', 'sort_order' => 1]);
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $this->createDriver($school->id, 'In A', null, $areaA->id);
        $inB = $this->createDriver($school->id, 'In B', null, $areaB->id);

        $ids = Driver::query()
            ->tap(fn ($q) => app(DriverIraqLocationFilter::class)->apply($q, 0, $areaA->id, 0))
            ->pluck('id')
            ->all();

        $this->assertNotContains($inB->id, $ids);
    }

    public function test_sub_district_scope_matches_only_linked_neighborhood(): void
    {
        $gov = District::query()->create(['name' => 'Gov', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $gov->id, 'name' => 'Area', 'sort_order' => 0]);
        $subA = Neighborhood::query()->create(['area_id' => $area->id, 'name' => 'Sub A', 'sort_order' => 0]);
        $subB = Neighborhood::query()->create(['area_id' => $area->id, 'name' => 'Sub B', 'sort_order' => 1]);
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverA = $this->createDriver($school->id, 'Driver A', $gov->id, $area->id);
        $driverA->neighborhoods()->attach($subA->id);
        $driverB = $this->createDriver($school->id, 'Driver B', $gov->id, $area->id);
        $driverB->neighborhoods()->attach($subB->id);

        $ids = Driver::query()
            ->tap(fn ($q) => app(DriverIraqLocationFilter::class)->apply($q, 0, 0, $subA->id))
            ->pluck('id')
            ->all();

        $this->assertSame([$driverA->id], $ids);
    }

    private function createDriver(int $schoolId, string $suffix, ?int $districtId, ?int $areaId): Driver
    {
        $this->phoneCounter++;

        return Driver::query()->create([
            'school_id' => $schoolId,
            'district_id' => $districtId,
            'area_id' => $areaId,
            'first_name' => $suffix,
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'Driver',
            'age' => 30,
            'id_card_number' => strtoupper(str_replace(' ', '-', $suffix)),
            'license_number' => 'LIC-'.strtoupper(str_replace(' ', '-', $suffix)),
            'primary_phone' => (string) $this->phoneCounter,
            'emergency_phone' => (string) ($this->phoneCounter + 100000),
            'residential_address' => 'Address',
            'status' => 'active',
        ]);
    }
}
