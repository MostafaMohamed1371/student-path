<?php

namespace Tests\Unit;

use App\Models\Area;
use App\Models\District;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\TransportRoute;
use App\Services\Routes\RouteIraqLocationFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteIraqLocationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_governorate_scope_includes_routes_in_child_areas_and_neighborhoods(): void
    {
        $gov = District::query()->create(['name' => 'Gov', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $gov->id, 'name' => 'Area', 'sort_order' => 0]);
        $sub = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Sub',
            'sort_order' => 0,
            'latitude' => 33.31,
            'longitude' => 44.36,
        ]);
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $byGov = TransportRoute::query()->create([
            'school_id' => $school->id,
            'district_id' => $gov->id,
            'name' => 'By Gov',
            'trip_type' => 'MORNING_PICKUP',
            'status' => 'active',
        ]);
        $byArea = TransportRoute::query()->create([
            'school_id' => $school->id,
            'area_id' => $area->id,
            'name' => 'By Area',
            'trip_type' => 'MORNING_PICKUP',
            'status' => 'active',
        ]);
        $bySub = TransportRoute::query()->create([
            'school_id' => $school->id,
            'area_id' => $area->id,
            'district_id' => $gov->id,
            'name' => 'By Sub',
            'trip_type' => 'MORNING_PICKUP',
            'status' => 'active',
        ]);
        $bySub->neighborhoods()->attach($sub->id);

        $ids = TransportRoute::query()
            ->tap(fn ($q) => app(RouteIraqLocationFilter::class)->apply($q, $gov->id, 0, 0))
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$byGov->id, $byArea->id, $bySub->id],
            $ids,
        );
    }

    public function test_area_scope_does_not_include_other_areas_in_same_governorate(): void
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

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'area_id' => $areaA->id,
            'name' => 'In A',
            'trip_type' => 'MORNING_PICKUP',
            'status' => 'active',
        ]);
        $inB = TransportRoute::query()->create([
            'school_id' => $school->id,
            'area_id' => $areaB->id,
            'name' => 'In B',
            'trip_type' => 'MORNING_PICKUP',
            'status' => 'active',
        ]);

        $ids = TransportRoute::query()
            ->tap(fn ($q) => app(RouteIraqLocationFilter::class)->apply($q, 0, $areaA->id, 0))
            ->pluck('id')
            ->all();

        $this->assertNotContains($inB->id, $ids);
    }

    public function test_area_filter_excludes_route_in_sibling_area_with_coordinates(): void
    {
        $gov = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $rusafa = Area::query()->create(['district_id' => $gov->id, 'name' => 'Rusafa', 'sort_order' => 0]);
        $karkh = Area::query()->create(['district_id' => $gov->id, 'name' => 'Karkh', 'sort_order' => 1]);
        Neighborhood::query()->create([
            'area_id' => $rusafa->id,
            'name' => 'Al-Karrada',
            'sort_order' => 0,
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'district_id' => $gov->id,
            'area_id' => $rusafa->id,
            'name' => 'Route Karrada',
            'trip_type' => 'MORNING_PICKUP',
            'start_address' => 'Start A',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);
        $karkhRoute = TransportRoute::query()->create([
            'school_id' => $school->id,
            'district_id' => $gov->id,
            'area_id' => $karkh->id,
            'name' => 'Route Karkh',
            'trip_type' => 'EVENING_PICKUP',
            'start_address' => 'Start Kh',
            'start_latitude' => 33.312,
            'start_longitude' => 44.362,
            'status' => 'active',
        ]);

        $ids = TransportRoute::query()
            ->tap(fn ($q) => app(RouteIraqLocationFilter::class)->apply($q, $gov->id, $rusafa->id, 0))
            ->pluck('name', 'id')
            ->all();

        $this->assertArrayHasKey(
            TransportRoute::query()->where('name', 'Route Karrada')->value('id'),
            $ids,
        );
        $this->assertNotContains($karkhRoute->id, array_keys($ids));
    }
}
