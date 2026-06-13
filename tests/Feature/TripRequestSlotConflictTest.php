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
use App\Models\InAppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripRequestSlotConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_request_pickup_and_return_for_same_student_same_day(): void
    {
        [$school, $student, $user, $driver] = $this->seedStudentWithDriver();

        $pickupTrip = $this->makeTrip($school, $driver, TripType::MORNING_PICKUP);
        $returnTrip = $this->makeTrip($school, $driver, TripType::MORNING_RETURN);

        Sanctum::actingAs($user);

        $pickup = $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $pickupTrip->id,
        ])->assertStatus(201);

        $return = $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $returnTrip->id,
        ])->assertOk();

        $this->assertNotSame((int) $pickup->json('data.id'), (int) $return->json('data.id'));
        $this->assertSame(
            2,
            TripRequest::query()
                ->where('student_id', $student->id)
                ->where('status', 'pending')
                ->count(),
        );
    }

    public function test_accepting_one_driver_rejects_competing_pending_requests_for_same_slot(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverB = $this->makeSecondDriver($school, 'Driver B');

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        $requestA = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'pending',
        ]);

        $requestB = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'pending',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $requestA), [
            'status' => 'accepted',
        ])->assertRedirect(route('dashboard.trip_requests.show', $requestA));

        $requestA->refresh();
        $requestB->refresh();

        $this->assertSame('accepted', $requestA->status);
        $this->assertSame('rejected', $requestB->status);
    }

    public function test_parent_can_create_requests_to_two_drivers_for_same_pickup_slot(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverB = $this->makeSecondDriver($school, 'Driver B');

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $pickupTripB = $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        Sanctum::actingAs($user);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
        ])->assertStatus(201);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripB->id,
        ])->assertStatus(201);

        $this->assertSame(
            2,
            TripRequest::query()
                ->where('student_id', $student->id)
                ->where('status', 'pending')
                ->count(),
        );
    }

    public function test_second_driver_cannot_accept_after_first_driver_accepted_same_pickup_slot(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverB = $this->makeSecondDriver($school, 'Driver B');
        $driverBUser = User::query()->findOrFail((int) $driverB->user_id);

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $pickupTripB = $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        $requestA = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'accepted',
            'updated_at' => now(),
        ]);

        $requestB = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driverBUser);

        $response = $this->putJson('/api/trip-requests/'.$requestB->id, [
            'status' => 'accepted',
        ])->assertStatus(422);

        $response->assertJsonPath(
            'message',
            __('dashboard.trip_request_slot_taken_by_another_driver'),
        );

        $this->assertSame('rejected', $requestB->fresh()->status);
        $this->assertSame('accepted', $requestA->fresh()->status);
    }

    public function test_stale_pending_order_removed_from_driver_list_after_another_driver_accepted(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverB = $this->makeSecondDriver($school, 'Driver B');
        $driverBUser = User::query()->findOrFail((int) $driverB->user_id);

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $pickupTripB = $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'accepted',
            'updated_at' => now(),
        ]);

        $requestB = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driverBUser);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.pending_count', 0)
            ->assertJsonCount(0, 'data.orders');

        $this->assertSame('rejected', $requestB->fresh()->status);
    }

    public function test_second_driver_sees_slot_taken_message_when_competing_request_was_auto_rejected(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverAUser = User::query()->findOrFail((int) $driverA->user_id);
        $driverB = $this->makeSecondDriver($school, 'Driver B');
        $driverBUser = User::query()->findOrFail((int) $driverB->user_id);

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $pickupTripB = $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        $requestA = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'pending',
        ]);

        $requestB = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driverAUser);
        $this->putJson('/api/trip-requests/'.$requestA->id, [
            'status' => 'accepted',
        ])->assertOk();

        $requestB->refresh();
        $this->assertSame('rejected', $requestB->status);

        Sanctum::actingAs($driverBUser);
        $response = $this->putJson('/api/trip-requests/'.$requestB->id, [
            'status' => 'accepted',
        ])->assertStatus(422);

        $response->assertJsonPath(
            'message',
            __('dashboard.trip_request_slot_taken_by_another_driver'),
        );
    }

    public function test_driver_accept_via_api_rejects_competing_pending_requests_for_same_slot(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverAUser = User::query()->findOrFail((int) $driverA->user_id);
        $driverB = $this->makeSecondDriver($school, 'Driver B');

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $pickupTripB = $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        $requestA = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'pending',
        ]);

        $requestB = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driverAUser);

        $this->putJson('/api/trip-requests/'.$requestA->id, [
            'status' => 'accepted',
        ])->assertOk();

        $requestA->refresh();
        $requestB->refresh();

        $this->assertSame('accepted', $requestA->status);
        $this->assertSame('rejected', $requestB->status);

        $parentNotifications = InAppNotification::query()
            ->where('user_id', $user->id)
            ->get();
        $this->assertCount(1, $parentNotifications);
        $this->assertSame('TRIP_REQUEST_ACCEPTED', $parentNotifications->first()->data['type'] ?? null);
        $this->assertSame($requestA->id, $parentNotifications->first()->data['trip_request_id'] ?? null);
    }

    public function test_driver_reject_notifies_parent_via_fcm_row(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverAUser = User::query()->findOrFail((int) $driverA->user_id);

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);

        $requestA = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driverAUser);
        $this->putJson('/api/trip-requests/'.$requestA->id, [
            'status' => 'rejected',
        ])->assertOk();

        $notification = InAppNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('TRIP_REQUEST_REJECTED', $notification->data['type'] ?? null);
        $this->assertSame($requestA->id, $notification->data['trip_request_id'] ?? null);
        $this->assertSame('rejected', $notification->data['status'] ?? null);
    }

    public function test_parent_request_notifies_driver_via_fcm_row(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverAUser = User::query()->findOrFail((int) $driverA->user_id);
        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);

        Sanctum::actingAs($user);
        $created = $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
        ])->assertStatus(201);

        $requestId = (int) $created->json('data.id');

        $notification = InAppNotification::query()
            ->where('user_id', $driverAUser->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('TRIP_REQUEST', $notification->data['type'] ?? null);
        $this->assertSame($requestId, $notification->data['trip_request_id'] ?? null);
    }

    public function test_accepting_pickup_does_not_reject_pending_return_request(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverB = $this->makeSecondDriver($school, 'Driver B');

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $returnTripB = $this->makeTrip($school, $driverB, TripType::MORNING_RETURN);

        $pickupRequest = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'pending',
        ]);

        $returnRequest = TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $returnTripB->id,
            'status' => 'pending',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $pickupRequest), [
            'status' => 'accepted',
        ])->assertRedirect();

        $pickupRequest->refresh();
        $returnRequest->refresh();

        $this->assertSame('accepted', $pickupRequest->status);
        $this->assertSame('pending', $returnRequest->status);
    }

    public function test_parent_cannot_request_same_slot_after_another_driver_accepted(): void
    {
        [$school, $student, $user, $driverA] = $this->seedStudentWithDriver('Driver A');
        $driverB = $this->makeSecondDriver($school, 'Driver B');

        $pickupTripA = $this->makeTrip($school, $driverA, TripType::MORNING_PICKUP);
        $pickupTripB = $this->makeTrip($school, $driverB, TripType::MORNING_PICKUP);

        TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverA->id,
            'trip_history_id' => $pickupTripA->id,
            'status' => 'accepted',
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driverB->id,
            'trip_history_id' => $pickupTripB->id,
        ])->assertStatus(422);
    }

    /**
     * @return array{0: School, 1: Student, 2: User, 3: Driver}
     */
    private function seedStudentWithDriver(string $driverFirstName = 'Slot'): array
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Slot School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian Slot',
            'phone' => '7300000999',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Slot Student',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000999',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Mansour',
            'nearest_landmark' => 'Center',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => $driverFirstName,
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'SLOT-'.$driverFirstName,
            'license_number' => 'LIC-'.$driverFirstName,
            'primary_phone' => '7900999900',
            'emergency_phone' => '7900999901',
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

        return [$school, $student, $user, $driver];
    }

    private function makeSecondDriver(School $school, string $firstName): Driver
    {
        $driverUser = User::factory()->create();

        return Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => $driverUser->id,
            'first_name' => $firstName,
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'Two',
            'age' => 36,
            'id_card_number' => 'SLOT-'.$firstName,
            'license_number' => 'LIC-'.$firstName,
            'primary_phone' => '7900888800',
            'emergency_phone' => '7900888801',
            'residential_address' => 'Baghdad',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
    }

    private function makeTrip(School $school, Driver $driver, TripType $tripType): TripHistory
    {
        return TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => $tripType->value,
            'bus_number' => '145',
            'route_title' => 'Route '.$tripType->value,
            'location' => 'Depot',
            'students_count' => 0,
            'distance_km' => 3,
            'start_time' => now()->setTime(7, 0),
            'end_time' => now()->setTime(8, 0),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);
    }
}
