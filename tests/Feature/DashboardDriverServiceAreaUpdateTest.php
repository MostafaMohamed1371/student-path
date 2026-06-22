<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Driver;
use App\Models\DriverServiceArea;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDriverServiceAreaUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_update_without_service_areas_preserves_address_information(): void
    {
        [$school, $driver, $serviceArea, $neighborhood] = $this->seedDriverWithServiceArea();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->put(route('dashboard.drivers.update', $driver), $this->driverPayload($school->id, $driver))
            ->assertRedirect(route('dashboard.drivers.index'));

        $driver->refresh();
        $this->assertSame('Updated address only', $driver->residential_address);
        $this->assertSame(1, $driver->serviceAreas()->count());
        $this->assertDatabaseHas('driver_service_areas', [
            'id' => $serviceArea->id,
            'driver_id' => $driver->id,
            'monthly_subscription_price' => 65000,
        ]);
        $this->assertTrue(
            $serviceArea->fresh()->neighborhoods()->whereKey($neighborhood->id)->exists(),
        );
    }

    public function test_driver_update_with_service_areas_updates_address_information(): void
    {
        [$school, $driver, $serviceArea, $neighborhood] = $this->seedDriverWithServiceArea();

        $otherNeighborhood = Neighborhood::query()->create([
            'area_id' => $neighborhood->area_id,
            'name' => 'Other sub-district',
            'sort_order' => 1,
            'latitude' => 33.32,
            'longitude' => 44.37,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $payload = $this->driverPayload($school->id, $driver);
        $payload['service_areas'] = [[
            'district_id' => $serviceArea->district_id,
            'area_id' => $serviceArea->area_id,
            'neighborhood_id' => $otherNeighborhood->id,
            'monthly_subscription_price' => 80000,
        ]];

        $this->put(route('dashboard.drivers.update', $driver), $payload)
            ->assertRedirect(route('dashboard.drivers.index'));

        $this->assertDatabaseHas('driver_service_areas', [
            'driver_id' => $driver->id,
            'monthly_subscription_price' => 80000,
        ]);

        $updated = DriverServiceArea::query()->where('driver_id', $driver->id)->firstOrFail();
        $this->assertTrue($updated->neighborhoods()->whereKey($otherNeighborhood->id)->exists());
        $this->assertFalse($updated->neighborhoods()->whereKey($neighborhood->id)->exists());
    }

    /**
     * @return array{0: School, 1: Driver, 2: DriverServiceArea, 3: Neighborhood}
     */
    private function seedDriverWithServiceArea(): array
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Service Area School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 0]);
        $neighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Near stop',
            'sort_order' => 0,
            'latitude' => 33.311,
            'longitude' => 44.361,
        ]);

        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 35,
            'id_card_number' => 'DRV-SA-1',
            'license_number' => 'LIC-SA-1',
            'primary_phone' => '7900666100',
            'emergency_phone' => '7900666101',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 65000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->sync([$neighborhood->id]);

        return [$school, $driver, $serviceArea, $neighborhood];
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(int $schoolId, Driver $driver): array
    {
        return [
            'school_id' => $schoolId,
            'first_name' => $driver->first_name,
            'father_name' => $driver->father_name,
            'grandfather_name' => $driver->grandfather_name,
            'last_name' => $driver->last_name,
            'age' => $driver->age,
            'id_card_number' => $driver->id_card_number,
            'license_number' => $driver->license_number,
            'primary_phone' => $driver->primary_phone,
            'emergency_phone' => $driver->emergency_phone,
            'residential_address' => 'Updated address only',
            'status' => $driver->status,
            'shift_period' => $driver->shift_period,
        ];
    }
}
