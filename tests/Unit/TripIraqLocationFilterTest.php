<?php

namespace Tests\Unit;

use App\Models\Area;
use App\Models\District;
use App\Models\Driver;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\TripHistory;
use App\Services\Trips\TripIraqLocationFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripIraqLocationFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneCounter = 7900200000;

    public function test_governorate_scope_matches_trips_by_driver_location(): void
    {
        $gov = District::query()->create(['name' => 'Gov', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $gov->id, 'name' => 'Area', 'sort_order' => 0]);
        $otherGov = District::query()->create(['name' => 'Other', 'sort_order' => 2]);
        $otherArea = Area::query()->create(['district_id' => $otherGov->id, 'name' => 'Other Area', 'sort_order' => 0]);
        $school = $this->createSchool();

        $inGovDriver = $this->createDriver($school->id, $gov->id, $area->id);
        $otherDriver = $this->createDriver($school->id, $otherGov->id, $otherArea->id);

        $inGovTrip = $this->createTrip($school->id, $inGovDriver->id, 'TRIP-GOV');
        $this->createTrip($school->id, $otherDriver->id, 'TRIP-OTHER');

        $ids = TripHistory::query()
            ->tap(fn ($q) => app(TripIraqLocationFilter::class)->apply($q, $gov->id, 0, 0))
            ->pluck('id')
            ->all();

        $this->assertSame([$inGovTrip->id], $ids);
    }

    public function test_sub_district_scope_matches_trip_start_near_neighborhood(): void
    {
        $gov = District::query()->create(['name' => 'Gov', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $gov->id, 'name' => 'Area', 'sort_order' => 0]);
        $sub = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Sub',
            'sort_order' => 0,
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);
        $school = $this->createSchool();
        $driver = $this->createDriver($school->id, null, null);

        $nearTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'NEAR',
            'start_latitude' => 33.3153,
            'start_longitude' => 44.3662,
            'start_time' => now(),
            'status' => 'PRESENT',
        ]);
        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'FAR',
            'start_time' => now(),
            'status' => 'PRESENT',
        ]);

        $ids = TripHistory::query()
            ->tap(fn ($q) => app(TripIraqLocationFilter::class)->apply($q, 0, 0, $sub->id))
            ->pluck('id')
            ->all();

        $this->assertSame([$nearTrip->id], $ids);
    }

    private function createSchool(): School
    {
        return School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
    }

    private function createDriver(int $schoolId, ?int $districtId, ?int $areaId): Driver
    {
        $this->phoneCounter++;

        return Driver::query()->create([
            'school_id' => $schoolId,
            'district_id' => $districtId,
            'area_id' => $areaId,
            'first_name' => 'Trip',
            'father_name' => 'Filter',
            'grandfather_name' => 'X',
            'last_name' => 'Driver',
            'age' => 30,
            'id_card_number' => 'TRIP-'.$this->phoneCounter,
            'license_number' => 'LIC-'.$this->phoneCounter,
            'primary_phone' => (string) $this->phoneCounter,
            'emergency_phone' => (string) ($this->phoneCounter + 100000),
            'residential_address' => 'Address',
            'status' => 'active',
        ]);
    }

    private function createTrip(int $schoolId, int $driverId, string $busNumber): TripHistory
    {
        return TripHistory::query()->create([
            'school_id' => $schoolId,
            'driver_id' => $driverId,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => $busNumber,
            'start_time' => now(),
            'status' => 'PRESENT',
        ]);
    }
}
