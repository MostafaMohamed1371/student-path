<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Area;
use App\Models\Bus;
use App\Models\District;
use App\Models\Driver;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_routes_page_and_create_route_with_start_point(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'H',
            'grandfather_name' => 'H',
            'last_name' => 'D',
            'age' => 35,
            'id_card_number' => 'IDC-R',
            'license_number' => 'LIC-R',
            'primary_phone' => '7770000200',
            'emergency_phone' => '7770000201',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'B-1',
            'color' => 'white',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.routes.index'))
            ->assertOk()
            ->assertSee(__('dashboard.menu_routes'), false)
            ->assertSee(__('dashboard.report_filter_all_schools'), false);

        Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Student On Route',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000100',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000100',
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => 33.312,
            'longitude' => 44.362,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $this->post(route('dashboard.routes.store'), [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'start_address' => 'Start depot, Baghdad',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
            'monthly_subscription_price' => 65000,
        ])->assertRedirect();

        $route = TransportRoute::query()->where('school_id', $school->id)->first();
        $this->assertNotNull($route);
        $this->assertNull($route->driver_id);
        $this->assertSame(65000, (int) $route->monthly_subscription_price);

        $this->post(route('dashboard.routes.assign_driver', $route), [
            'driver_id' => $driver->id,
        ])->assertRedirect();

        $driver->refresh();
        $this->assertSame(65000, $driver->monthly_subscription_price);
        $this->assertSame('MORNING', $driver->shift_period);
        $this->assertSame(1, TransportRouteStudent::query()->count());

        $this->get(route('dashboard.routes.index', ['school_id' => $school->id]))
            ->assertOk()
            ->assertSee('Start depot, Baghdad', false);
    }

    public function test_routes_index_lists_all_routes_when_filters_are_empty(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'Route School A',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'Route School B',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        $driverA = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $schoolA->id,
            'first_name' => 'A',
            'father_name' => 'D',
            'grandfather_name' => 'R',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-RA',
            'license_number' => 'LIC-RA',
            'primary_phone' => '7770000401',
            'emergency_phone' => '7770001401',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $driverB = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $schoolB->id,
            'first_name' => 'B',
            'father_name' => 'D',
            'grandfather_name' => 'R',
            'last_name' => 'Two',
            'age' => 31,
            'id_card_number' => 'IDC-RB',
            'license_number' => 'LIC-RB',
            'primary_phone' => '7770000402',
            'emergency_phone' => '7770001402',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $schoolA->id,
            'driver_id' => $driverA->id,
            'name' => 'Route Morning A',
            'shift_period' => 'MORNING',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'start_address' => 'Start A',
            'status' => 'active',
        ]);
        TransportRoute::query()->create([
            'school_id' => $schoolB->id,
            'driver_id' => $driverB->id,
            'name' => 'Route Evening B',
            'shift_period' => 'EVENING',
            'trip_type' => TripType::EVENING_PICKUP->value,
            'start_address' => 'Start B',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.routes.index'))
            ->assertOk()
            ->assertSee('Route Morning A', false)
            ->assertSee('Route Evening B', false);

        $this->get(route('dashboard.routes.index', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertSee('Route Morning A', false)
            ->assertDontSee('Route Evening B', false);

        $this->get(route('dashboard.routes.index', ['shift_period' => 'EVENING']))
            ->assertOk()
            ->assertSee('Route Evening B', false)
            ->assertDontSee('Route Morning A', false);
    }

    public function test_form_options_returns_drivers_for_school_and_trip_type(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-FO',
            'license_number' => 'LIC-FO',
            'primary_phone' => '7770000300',
            'emergency_phone' => '7770000301',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'B-2',
            'color' => 'white',
            'capacity' => 10,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route D',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $this->getJson(route('dashboard.routes.form_options', [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'drivers')
            ->assertJsonPath('route_drivers.0.id', $driver->id);

        Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Near Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000200',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000200',
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => 33.312,
            'longitude' => 44.362,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Far Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000201',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000201',
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => 33.40,
            'longitude' => 44.50,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $this->post(route('dashboard.routes.assign_route_matching'), [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'driver_id' => $driver->id,
        ])->assertRedirect();

        $this->assertSame(1, TransportRouteStudent::query()->count());
    }

    public function test_cannot_create_duplicate_route_for_same_driver_and_trip_type(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Dup',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'L',
            'age' => 30,
            'id_card_number' => 'IDC-DUP',
            'license_number' => 'LIC-DUP',
            'primary_phone' => '7770000400',
            'emergency_phone' => '7770000401',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'name' => 'Bus',
            'type' => 'Coaster',
            'city' => 'Baghdad',
            'number' => 'B-DUP',
            'color' => 'white',
            'capacity' => 10,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Existing',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Start',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('dashboard.routes.store'), [
            'school_id' => $school->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'start_address' => 'Another start',
            'start_latitude' => 33.312,
            'start_longitude' => 44.362,
            'status' => 'active',
        ])->assertRedirect();

        $secondRoute = TransportRoute::query()->where('start_address', 'Another start')->first();
        $this->assertNotNull($secondRoute);

        $this->post(route('dashboard.routes.assign_driver', $secondRoute), [
            'driver_id' => $driver->id,
        ])->assertSessionHasErrors('driver_id');

        $this->assertSame(2, TransportRoute::query()->count());
    }

    public function test_assigned_driver_in_route_report_lists_driver_on_route(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Assigned School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Sam',
            'father_name' => 'F',
            'grandfather_name' => 'G',
            'last_name' => 'Driver',
            'age' => 30,
            'id_card_number' => 'IDC-AD',
            'license_number' => 'LIC-AD',
            'primary_phone' => '7770000500',
            'emergency_phone' => '7770000501',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route AD',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'monthly_subscription_price' => 70000,
            'start_address' => 'Start AD',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.assigned_drivers.index'))
            ->assertOk()
            ->assertSee(__('dashboard.menu_assigned_driver_in_route'), false)
            ->assertSee('Sam Driver', false)
            ->assertSee('Route AD', false)
            ->assertSee('70,000', false)
            ->assertSee(__('dashboard.assign_driver'), false);

        $this->get(route('dashboard.routes.index'))
            ->assertOk()
            ->assertSee('Route AD', false);
    }

    public function test_routes_can_be_filtered_by_iraq_location(): void
    {
        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $district = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Rusafa', 'sort_order' => 0]);
        $subDistrict = Neighborhood::query()->create([
            'area_id' => $district->id,
            'name' => 'Al-Karrada',
            'sort_order' => 0,
            'latitude' => 33.3152,
            'longitude' => 44.3661,
        ]);
        $otherArea = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 1]);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Location School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        TransportRoute::query()->create([
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $district->id,
            'neighborhood_id' => $subDistrict->id,
            'name' => 'Route Karrada',
            'trip_type' => TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Start K',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ]);
        TransportRoute::query()->create([
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $otherArea->id,
            'name' => 'Route Karkh',
            'trip_type' => TripType::EVENING_PICKUP->value,
            'shift_period' => 'EVENING',
            'start_address' => 'Start Kh',
            'start_latitude' => 33.312,
            'start_longitude' => 44.362,
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.routes.index', [
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $district->id,
        ]))
            ->assertOk()
            ->assertSee('Route Karrada', false)
            ->assertDontSee('Route Karkh', false);

        $this->get(route('dashboard.routes.index', [
            'school_id' => $school->id,
            'district_id' => $governorate->id,
            'area_id' => $district->id,
            'neighborhood_id' => $subDistrict->id,
        ]))
            ->assertOk()
            ->assertSee('Route Karrada', false)
            ->assertDontSee('Route Karkh', false);
    }
}
