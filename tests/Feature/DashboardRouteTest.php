<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
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

        $this->get(route('dashboard.routes.index', ['school_id' => $school->id]))
            ->assertOk()
            ->assertSee(__('dashboard.menu_routes'), false);

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
            'driver_id' => $driver->id,
            'start_address' => 'Start depot, Baghdad',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('transport_routes', [
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'start_address' => 'Start depot, Baghdad',
        ]);

        $this->assertSame(1, TransportRouteStudent::query()->count());
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
            'driver_id' => $driver->id,
            'start_address' => 'Another start',
            'start_latitude' => 33.312,
            'start_longitude' => 44.362,
            'status' => 'active',
        ])
            ->assertSessionHasErrors('driver_id');

        $this->assertSame(1, TransportRoute::query()->count());
    }
}
