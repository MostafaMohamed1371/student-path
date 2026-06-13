<?php

namespace Tests\Feature;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTripRequestEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_edit_all_trip_request_fields_including_status(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Edit School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian Edit',
            'phone' => '7300000888',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Edit Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000888',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Edit',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'EDIT-DRV',
            'license_number' => 'EDIT-LIC',
            'primary_phone' => '7900888800',
            'emergency_phone' => '7900888801',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'school_id' => $school->id,
            'name' => 'Bus',
            'type' => 'Van',
            'city' => 'Baghdad',
            'number' => '145',
            'color' => 'yellow',
            'capacity' => 20,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '145',
            'route_title' => 'Morning pickup',
            'location' => 'Depot',
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => now()->setTime(7, 0),
            'end_time' => now()->setTime(8, 0),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'rejected',
            'notes' => 'Old note',
            'present_type' => 'صباحي',
            'moving_point' => 'Home',
            'stop_point' => 'School',
            'subscribe_price' => 50000,
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->get(route('dashboard.trip_requests.edit', $tripRequest))->assertOk();

        $this->put(route('dashboard.trip_requests.update', $tripRequest), [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'cancelled',
            'notes' => 'Updated note',
            'present_type' => 'صباحي - عودة',
            'moving_point' => 'School gate',
            'stop_point' => 'Home address',
            'subscribe_price' => 75000,
        ])->assertRedirect(route('dashboard.trip_requests.show', $tripRequest));

        $tripRequest->refresh();

        $this->assertSame('cancelled', $tripRequest->status);
        $this->assertSame('Updated note', $tripRequest->notes);
        $this->assertSame('صباحي - عودة', $tripRequest->present_type);
        $this->assertSame('School gate', $tripRequest->moving_point);
        $this->assertSame('Home address', $tripRequest->stop_point);
        $this->assertSame(75000.0, (float) $tripRequest->subscribe_price);
        $this->assertNotNull($tripRequest->cancelled_at);
    }

    public function test_staff_can_accept_pending_trip_request_from_edit_form(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Accept Edit School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian Accept',
            'phone' => '7300000777',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Accept Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000777',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => 'Accept',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'ACC-DRV',
            'license_number' => 'ACC-LIC',
            'primary_phone' => '7900777700',
            'emergency_phone' => '7900777701',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '145',
            'route_title' => 'Morning pickup',
            'location' => 'Depot',
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => now()->setTime(7, 0),
            'end_time' => now()->setTime(8, 0),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'pending',
            'present_type' => 'صباحي',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update', $tripRequest), [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'accepted',
            'notes' => null,
            'present_type' => 'صباحي',
            'moving_point' => null,
            'stop_point' => null,
            'subscribe_price' => null,
        ])->assertRedirect(route('dashboard.trip_requests.show', $tripRequest));

        $tripRequest->refresh();

        $this->assertSame('accepted', $tripRequest->status);
        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_staff_can_delete_trip_request_in_any_status(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Delete School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian Delete',
            'phone' => '7300000666',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Delete Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000666',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'cancelled',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->delete(route('dashboard.trip_requests.destroy', $tripRequest))
            ->assertRedirect(route('dashboard.trip_requests.index'));

        $this->assertDatabaseMissing('trip_requests', ['id' => $tripRequest->id]);
    }
}
