<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Area;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\District;
use App\Models\DriverServiceArea;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\TripHistory;
use App\Services\Trips\DriverTripAutoProvisioner;
use App\Support\Geo\Haversine as SupportHaversine;
use App\Support\SchoolWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTripAutoProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private function todayDayKey(): string
    {
        return strtolower(now()->locale('en')->dayName);
    }

    public function test_auto_provisioning_creates_morning_pickup_and_return_for_today_work_day(): void
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $day = now($tz)->startOfDay();
        $todayKey = $this->todayDayKey();

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Auto Provision School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'School Address',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'status' => 'active',
            'work_days' => [$todayKey],
            'shift_period' => 'MORNING',
            'work_time_from' => '07:30',
            'work_time_to' => '14:00',
        ]);

        $district = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $district->id, 'name' => 'Rusafa', 'sort_order' => 0]);
        $neighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Sub 1',
            'sort_order' => 0,
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-AUTO-1',
            'license_number' => 'LIC-AUTO-1',
            'primary_phone' => '7900111001',
            'emergency_phone' => '7900111002',
            'residential_address' => 'Depot',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'school_id' => $school->id,
            'name' => 'Bus',
            'type' => 'Van',
            'city' => 'Baghdad',
            'number' => 'BUS-AUTO',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $district->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 70000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($neighborhood->id);

        app(DriverTripAutoProvisioner::class)->syncForDriver($driver->fresh(['school', 'bus', 'serviceAreas']));

        $pickup = TripHistory::query()
            ->where('school_id', $school->id)
            ->where('driver_id', $driver->id)
            ->where('trip_type', TripType::MORNING_PICKUP->value)
            ->firstOrFail();

        $return = TripHistory::query()
            ->where('school_id', $school->id)
            ->where('driver_id', $driver->id)
            ->where('trip_type', TripType::MORNING_RETURN->value)
            ->firstOrFail();

        $shiftStart = $day->copy()->setTime(7, 30);
        $pickupExpectedStart = $shiftStart->copy()->subMinutes(SchoolWorkSchedule::PICKUP_LEAD_MINUTES);
        $pickupExpectedEnd = $shiftStart;

        $returnExpectedStart = $day->copy()->setTime(14, 0);
        $returnExpectedEnd = $returnExpectedStart->copy()->addMinutes($pickupExpectedStart->diffInMinutes($pickupExpectedEnd));

        $this->assertTrue($pickup->start_time?->equalTo($pickupExpectedStart));
        $this->assertTrue($pickup->end_time?->equalTo($pickupExpectedEnd));

        $this->assertTrue($return->start_time?->equalTo($returnExpectedStart));
        $this->assertTrue($return->end_time?->equalTo($returnExpectedEnd));

        $this->assertTrue($pickup->auto_schedule_work_days);
        $this->assertTrue($return->auto_schedule_work_days);

        $this->assertSame((float) $neighborhood->latitude, (float) $pickup->start_latitude);
        $this->assertSame((float) $neighborhood->longitude, (float) $pickup->start_longitude);

        $expectedDistance = round(
            SupportHaversine::metersBetween(
                (float) $neighborhood->latitude,
                (float) $neighborhood->longitude,
                (float) $school->latitude,
                (float) $school->longitude,
            ) / 1000,
            2,
        );
        $this->assertEqualsWithDelta((float) $expectedDistance, (float) $pickup->distance_km, 0.000001);
        $this->assertEqualsWithDelta((float) $expectedDistance, (float) $return->distance_km, 0.000001);
    }

    public function test_auto_provisioning_updates_open_trips_times_when_schedule_changes_today(): void
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $day = now($tz)->startOfDay();
        $todayKey = $this->todayDayKey();

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Update Schedule School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'School Address',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'status' => 'active',
            'work_days' => [$todayKey],
            'shift_period' => 'MORNING',
            'work_time_from' => '07:00',
            'work_time_to' => '14:00',
        ]);

        $district = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $district->id, 'name' => 'Rusafa', 'sort_order' => 0]);
        $neighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Sub 1',
            'sort_order' => 0,
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-AUTO-2',
            'license_number' => 'LIC-AUTO-2',
            'primary_phone' => '7900112001',
            'emergency_phone' => '7900112002',
            'residential_address' => 'Depot',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $district->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 70000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($neighborhood->id);

        $provisioner = app(DriverTripAutoProvisioner::class);
        $provisioner->syncForDriver($driver->fresh(['school', 'bus', 'serviceAreas']));

        $pickupBefore = TripHistory::query()
            ->where('driver_id', $driver->id)
            ->where('trip_type', TripType::MORNING_PICKUP->value)
            ->firstOrFail();

        $this->assertTrue($pickupBefore->start_time?->equalTo(
            $day->copy()->setTime(7, 0)->subMinutes(SchoolWorkSchedule::PICKUP_LEAD_MINUTES)
        ));

        $school->forceFill(['work_time_from' => '08:00'])->save();

        $provisioner->syncForDriver($driver->fresh(['school', 'bus', 'serviceAreas']));

        $pickupAfter = $pickupBefore->fresh();
        $this->assertTrue($pickupAfter->start_time?->equalTo(
            $day->copy()->setTime(8, 0)->subMinutes(SchoolWorkSchedule::PICKUP_LEAD_MINUTES)
        ));
        $this->assertTrue($pickupAfter->end_time?->equalTo($day->copy()->setTime(8, 0)));
    }

    public function test_auto_provisioning_creates_evening_pickup_and_return_for_today_work_day(): void
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $day = now($tz)->startOfDay();
        $todayKey = $this->todayDayKey();

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Evening Auto Provision School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'School Address',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'status' => 'active',
            'work_days' => [$todayKey],
            'shift_period' => 'BOTH',
            'work_time_from' => '07:30',
            'work_time_to' => '14:00',
            'evening_work_time_from' => '18:00',
            'evening_work_time_to' => '22:00',
        ]);

        $district = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $district->id, 'name' => 'Rusafa', 'sort_order' => 0]);
        $neighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Sub 1',
            'sort_order' => 0,
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-AUTO-3',
            'license_number' => 'LIC-AUTO-3',
            'primary_phone' => '7900111003',
            'emergency_phone' => '7900111004',
            'residential_address' => 'Depot',
            'status' => 'active',
            'shift_period' => 'EVENING',
        ]);

        $serviceArea = DriverServiceArea::query()->create([
            'driver_id' => $driver->id,
            'district_id' => $district->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 70000,
            'sort_order' => 0,
        ]);
        $serviceArea->neighborhoods()->attach($neighborhood->id);

        app(DriverTripAutoProvisioner::class)->syncForDriver($driver->fresh(['school', 'bus', 'serviceAreas']));

        $pickup = TripHistory::query()
            ->where('school_id', $school->id)
            ->where('driver_id', $driver->id)
            ->where('trip_type', TripType::EVENING_PICKUP->value)
            ->firstOrFail();

        $return = TripHistory::query()
            ->where('school_id', $school->id)
            ->where('driver_id', $driver->id)
            ->where('trip_type', TripType::EVENING_RETURN->value)
            ->firstOrFail();

        $shiftStart = $day->copy()->setTime(18, 0);
        $pickupExpectedStart = $shiftStart->copy()->subMinutes(SchoolWorkSchedule::PICKUP_LEAD_MINUTES);
        $pickupExpectedEnd = $shiftStart;

        $returnExpectedStart = $day->copy()->setTime(22, 0);
        $returnExpectedEnd = $returnExpectedStart->copy()->addMinutes(
            $pickupExpectedStart->diffInMinutes($pickupExpectedEnd),
        );

        $this->assertTrue($pickup->start_time?->equalTo($pickupExpectedStart));
        $this->assertTrue($pickup->end_time?->equalTo($pickupExpectedEnd));

        $this->assertTrue($return->start_time?->equalTo($returnExpectedStart));
        $this->assertTrue($return->end_time?->equalTo($returnExpectedEnd));
    }
}

