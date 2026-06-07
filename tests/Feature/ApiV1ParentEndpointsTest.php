<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Absence;
use App\Models\Bus;
use App\Models\District;
use App\Models\Driver;
use App\Models\DriverServiceArea;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\Neighborhood;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use Database\Seeders\IraqLocationsSeeder;
use Database\Seeders\LocationMetaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1ParentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_public_send_otp_matches_legacy_validation(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'phone' => '7711111111',
            'type_user' => 'driver',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['phone']]);

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_v1_auth_me_under_prefix(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user']]);
    }

    public function test_v1_profile_get(): void
    {
        $user = User::factory()->create(['name' => 'Parent User']);
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Parent User');
    }

    public function test_v1_wallet_recharge_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/recharge', ['amount' => 5], ['Idempotency-Key' => 'idem-test-1'])
            ->assertCreated()
            ->assertJsonPath('data.balance', '5.00');

        $this->postJson('/api/wallet/recharge', ['amount' => 999], ['Idempotency-Key' => 'idem-test-1'])
            ->assertOk()
            ->assertJsonPath('data.balance', '5.00');

        $this->getJson('/api/wallet/transactions')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_v1_home_location_show_and_store(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/home-location')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->postJson('/api/home-location', [
            'latitude' => 33.3152,
            'longitude' => 44.3661,
            'formatted_address' => 'Baghdad',
            'district_area' => 'Karrada',
            'nearest_landmark' => 'Near school',
            'place_id' => 'test_place',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.latitude', 33.3152)
            ->assertJsonPath('data.district_area', 'Karrada')
            ->assertJsonPath('data.nearest_landmark', 'Near school')
            ->assertJsonPath('data.has_location', true)
            ->assertJsonPath('data.place_id', 'test_place');

        $this->getJson('/api/home-location')
            ->assertOk()
            ->assertJsonPath('data.formatted_address', 'Near school')
            ->assertJsonPath('data.has_location', true);

        $this->putJson('/api/home-location', [
            'latitude' => 33.4,
            'longitude' => 44.4,
            'districtArea' => 'Updated area',
            'nearestLandmark' => 'Updated landmark',
        ])
            ->assertOk()
            ->assertJsonPath('data.latitude', 33.4)
            ->assertJsonPath('data.district_area', 'Updated area')
            ->assertJsonPath('data.nearest_landmark', 'Updated landmark');
    }

    public function test_v1_home_location_saved_from_mobile_is_visible_on_dashboard_guardian_form(): void
    {
        $school = $this->makeSchool('Shared Home Loc School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Mobile Parent',
            'phone' => '7900555123',
            'id_card_number' => 'MOBILE-LOC-1',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'phone' => '9647900555123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/home-location', [
            'latitude' => 33.3152,
            'longitude' => 44.3661,
            'district_area' => 'Karrada',
            'nearest_landmark' => 'Parent app pickup point',
        ])->assertStatus(201);

        $payload = app(\App\Services\Guardian\GuardianHomeLocationSync::class)
            ->homeLocationFieldsForGuardian($guardian->fresh());

        $this->assertSame(33.3152, $payload['home_latitude']);
        $this->assertSame(44.3661, $payload['home_longitude']);
        $this->assertSame('Karrada', $payload['home_district_area']);
        $this->assertSame('Parent app pickup point', $payload['home_nearest_landmark']);
    }

    public function test_v1_district_areas(): void
    {
        $this->seed(IraqLocationsSeeder::class);
        $this->seed(LocationMetaSeeder::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $districts = $this->getJson('/api/locations/districts')->assertOk()->json('data');
        $this->assertNotEmpty($districts);
        $districtId = (int) $districts[0]['id'];

        $this->getJson("/api/locations/districts/{$districtId}/areas")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJson(fn ($json) => $json->whereType('data', 'array')->etc());
    }

    public function test_v1_meta_schools_minimal_lists_id_and_display_name(): void
    {
        $school = $this->makeSchool('Checklist Academy');
        $user = User::factory()->create([
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/meta/schools?format=minimal')
            ->assertOk()
            ->assertJsonPath('data.0.id', $school->id)
            ->assertJsonPath('data.0.name', 'Checklist Academy');
    }

    public function test_v1_guardian_only_parent_has_school_scope_without_users_school_id(): void
    {
        $school = $this->makeSchool('Guardian Scoped School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian No User School',
            'phone' => '7300000099',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Scoped Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000099',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/meta/schools?format=minimal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $school->id);

        $this->getJson('/api/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('data.fullName', 'Scoped Child');

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '77',
            'start_time' => now()->startOfDay()->addHours(7),
            'end_time' => now()->startOfDay()->addHours(8),
            'status' => 'PRESENT',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        $this->getJson('/api/trips/'.$trip->id)
            ->assertOk()
            ->assertJsonPath('data.bus_number', '77');
    }

    public function test_v1_guardian_with_students_in_multiple_schools_gets_combined_scope(): void
    {
        $schoolA = $this->makeSchool('School Alpha');
        $schoolB = $this->makeSchool('School Beta');
        $guardian = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Multi School Parent',
            'phone' => '7300000077',
            'status' => 'active',
        ]);
        $studentA = Student::query()->create([
            'school_id' => $schoolA->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child A',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000071',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $studentB = Student::query()->create([
            'school_id' => $schoolB->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child B',
            'gender' => 'female',
            'grade' => '2',
            'student_phone' => '7400000072',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/meta/schools?format=minimal')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/students/'.$studentA->id)->assertOk()->assertJsonPath('data.fullName', 'Child A');
        $this->getJson('/api/students/'.$studentB->id)->assertOk()->assertJsonPath('data.fullName', 'Child B');

        $tripB = TripHistory::query()->create([
            'school_id' => $schoolB->id,
            'bus_number' => '88',
            'start_time' => now()->startOfDay()->addHours(10),
            'end_time' => now()->startOfDay()->addHours(11),
            'status' => 'PRESENT',
            'students_preview' => [['id' => (string) $studentB->id, 'name' => $studentB->full_name]],
        ]);

        $this->getJson('/api/trips/'.$tripB->id)
            ->assertOk()
            ->assertJsonPath('data.bus_number', '88');
    }

    public function test_v1_guardian_with_no_students_gets_empty_student_index(): void
    {
        $school = $this->makeSchool('Empty Guardian School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Lonely Guardian',
            'phone' => '7300000088',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_v1_parent_students_index_includes_linked_child(): void
    {
        $school = $this->makeSchool('Parent School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian G',
            'phone' => '7300000001',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child C',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000001',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fullName', 'Child C');

        $this->getJson('/api/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('data.fullName', 'Child C');
    }

    public function test_v1_parent_students_index_includes_children_across_school_guardian_rows(): void
    {
        $schoolA = $this->makeSchool('Parent School A');
        $schoolB = $this->makeSchool('Parent School B');
        $sharedPhone = '7300000100';
        $sharedIdCard = 'PARENT-MULTI-001';

        $guardianA = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Cross School Parent',
            'phone' => $sharedPhone,
            'id_card_number' => $sharedIdCard,
            'status' => 'active',
        ]);
        $guardianB = Guardian::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Cross School Parent',
            'phone' => $sharedPhone,
            'id_card_number' => $sharedIdCard,
            'status' => 'active',
        ]);

        Student::query()->create([
            'school_id' => $schoolA->id,
            'guardian_id' => $guardianA->id,
            'full_name' => 'Child School A',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000101',
            'guardian_name' => $guardianA->full_name,
            'guardian_primary_phone' => $guardianA->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        Student::query()->create([
            'school_id' => $schoolB->id,
            'guardian_id' => $guardianB->id,
            'full_name' => 'Child School B',
            'gender' => 'female',
            'grade' => '2',
            'student_phone' => '7400000102',
            'guardian_name' => $guardianB->full_name,
            'guardian_primary_phone' => $guardianB->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $parent = User::factory()->create([
            'phone' => '964'.$sharedPhone,
            'guardian_id' => $guardianA->id,
            'school_id' => null,
        ]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['fullName' => 'Child School A'])
            ->assertJsonFragment(['fullName' => 'Child School B']);
    }

    public function test_v1_school_staff_can_create_student_via_parent_students_endpoint(): void
    {
        $school = $this->makeSchool('Add Student School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian G2',
            'phone' => '7300000002',
            'status' => 'active',
        ]);
        $staff = User::factory()->create([
            'phone' => '9647908111111',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/students', [
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'New Child',
            'gender' => 'female',
            'grade' => '2',
            'student_phone' => '7400000002',
            'relationship' => 'mother',
            'district_area' => 'Zone A',
            'nearest_landmark' => 'School gate',
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.fullName', 'New Child');

        $this->assertDatabaseHas('students', [
            'full_name' => 'New Child',
            'guardian_id' => $guardian->id,
        ]);
        $newStudentId = Student::query()->where('full_name', 'New Child')->value('id');
        $this->assertNotNull($newStudentId);
        $this->assertDatabaseMissing('trip_requests', [
            'student_id' => $newStudentId,
        ]);
    }

    public function test_v1_guardian_cannot_create_student_via_parent_students_endpoint(): void
    {
        $school = $this->makeSchool('Guardian Block School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian Only',
            'phone' => '7300000099',
            'status' => 'active',
        ]);
        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => null,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($parent);

        $this->postJson('/api/students', [
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Blocked Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000099',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_v1_trips_available_active_show_and_driver(): void
    {
        $school = $this->makeSchool('Trip School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Trip',
            'phone' => '7300000003',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student Trip',
            'gender' => 'male',
            'grade' => '3',
            'student_phone' => '7400000003',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);
        $driverUser = User::factory()->create(['phone' => '9647909000003']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-T1',
            'license_number' => 'LIC-T1',
            'primary_phone' => '7770111113',
            'emergency_phone' => '7770222223',
            'residential_address' => 'R',
            'status' => 'active',
        ]);
        $busOwner = User::factory()->create(['phone' => '9647909000004']);
        Bus::query()->create([
            'user_id' => $busOwner->id,
            'driver_id' => $driver->id,
            'name' => 'Bus B',
            'number' => '200',
            'type' => 'standard',
            'city' => 'Baghdad',
            'capacity' => 40,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $dayStart = now()->startOfDay();
        $laterTrip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '200',
            'route_title' => 'Morning',
            'location' => 'Campus',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => $dayStart->copy()->addHours(8),
            'end_time' => $dayStart->copy()->addHours(9),
            'status' => 'PRESENT',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '200',
            'route_title' => 'Live',
            'location' => 'Road',
            'students_count' => 1,
            'distance_km' => 2,
            'start_time' => now()->subHour(),
            'end_time' => null,
            'status' => 'PRESENT',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        Sanctum::actingAs($user);

        $available = $this->getJson('/api/trips/available?student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->json('data');
        $this->assertTrue(collect($available)->contains(fn (array $row) => (int) $row['id'] === $laterTrip->id));

        $this->getJson('/api/trips/active?student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.trip.bus_number', '200');

        $this->getJson('/api/trips/'.$laterTrip->id)
            ->assertOk()
            ->assertJsonPath('data.route_title', 'Morning')
            ->assertJsonPath('data.distance_km', 1);

        $this->getJson('/api/trips/'.$laterTrip->id.'/driver')
            ->assertOk()
            ->assertJsonPath('data.driver.first_name', 'D')
            ->assertJsonPath('data.bus.number', '200');
    }

    public function test_v1_trip_requests_and_cancel(): void
    {
        $school = $this->makeSchool('Req School');
        $school->update(['latitude' => 33.3152, 'longitude' => 44.3661]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Req',
            'phone' => '7300000004',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Req',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000004',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $student->update(['latitude' => 33.325, 'longitude' => 44.376]);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '1',
            'start_time' => now()->addDay(),
            'end_time' => null,
            'status' => 'PRESENT',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($user);

        $driverUser = User::factory()->create(['phone' => '9647909000004']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Req',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-RQ',
            'license_number' => 'LIC-RQ',
            'primary_phone' => '7770000004',
            'emergency_phone' => '7770001004',
            'residential_address' => 'R',
            'status' => 'active',
        ]);

        $created = $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'notes' => 'Please',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.driverCard.driverId', (string) $driver->id)
            ->assertJsonPath('data.tripPreview.pickupLabel', 'Unknown pickup')
            ->assertJsonPath('data.tripPreview.destinationLabel', 'Req School');

        $dKm = $created->json('data.driverCard.distanceKm');
        $this->assertIsNumeric($dKm);
        $this->assertGreaterThan(0, (float) $dKm);

        $req = TripRequest::query()->firstOrFail();
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $driverUser->id,
        ]);
        $driverNotification = InAppNotification::query()
            ->where('user_id', $driverUser->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($driverNotification);
        $this->assertSame('TRIP_REQUEST', $driverNotification->data['type'] ?? null);
        $this->assertSame($req->id, $driverNotification->data['trip_request_id'] ?? null);

        $this->getJson('/api/trip-requests/'.$req->id)
            ->assertOk()
            ->assertJsonPath('data.id', $req->id)
            ->assertJsonPath('data.driverCard.driverId', (string) $driver->id)
            ->assertJsonPath('data.tripPreview.pickupLabel', 'Unknown pickup')
            ->assertJsonPath('data.tripPreview.destinationLabel', 'Req School');

        $this->getJson('/api/trip-requests')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.driverCard.driverId', (string) $driver->id)
            ->assertJsonPath('data.items.0.tripPreview.destinationLabel', 'Req School');

        $this->postJson('/api/trip-requests/'.$req->id.'/cancel')->assertOk();
        $this->assertSame('cancelled', $req->fresh()->status);
    }

    public function test_trip_request_store_does_not_create_duplicate_pending_for_same_student(): void
    {
        $school = $this->makeSchool('Dedup School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Dedup',
            'phone' => '7300000290',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Dedup',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000290',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($user);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Dedup',
            'father_name' => 'Driver',
            'grandfather_name' => 'D',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-DD',
            'license_number' => 'LIC-DD',
            'primary_phone' => '7770000290',
            'emergency_phone' => '7770001290',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $payload = [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'notes' => 'Book once',
        ];

        $first = $this->postJson('/api/trip-requests', $payload)
            ->assertStatus(201)
            ->assertJsonPath('message', 'Trip request created');

        $firstId = (int) $first->json('data.id');

        $second = $this->postJson('/api/trip-requests', $payload)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Trip request already pending');

        $this->assertSame($firstId, (int) $second->json('data.id'));
        $this->assertSame(
            1,
            TripRequest::query()
                ->where('user_id', $user->id)
                ->where('student_id', $student->id)
                ->where('status', 'pending')
                ->count(),
        );
        $this->assertSame(
            1,
            InAppNotification::query()->where('user_id', $driverUser->id)->count(),
        );

        $this->postJson('/api/trip-requests/'.$firstId.'/cancel')->assertOk();

        $third = $this->postJson('/api/trip-requests', $payload)->assertStatus(201);
        $this->assertNotSame($firstId, (int) $third->json('data.id'));
        $this->assertSame(
            2,
            InAppNotification::query()->where('user_id', $driverUser->id)->count(),
        );
    }

    public function test_trip_request_cancel_returns_422_when_not_pending(): void
    {
        $school = $this->makeSchool('Cancel Guard School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G C',
            'phone' => '7300000091',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S C',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000091',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'notes' => 'X',
        ])->assertStatus(201);

        $req = TripRequest::query()->firstOrFail();
        $req->update(['status' => 'accepted']);

        $this->postJson('/api/trip-requests/'.$req->id.'/cancel')
            ->assertStatus(422);
    }

    public function test_v1_absences_index_and_show(): void
    {
        $school = $this->makeSchool('Abs School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Abs',
            'phone' => '7300000005',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Abs',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000005',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647908000005']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'A',
            'grandfather_name' => 'B',
            'last_name' => 'C',
            'age' => 35,
            'id_card_number' => 'IDC-ABS-LIST',
            'license_number' => 'LIC-ABS-LIST',
            'primary_phone' => '7770000005',
            'emergency_phone' => '7770000006',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $route = \App\Models\TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route Abs List',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot',
            'start_latitude' => 33.3,
            'start_longitude' => 44.3,
            'status' => 'active',
        ]);
        \App\Models\TransportRouteStudent::query()->create([
            'transport_route_id' => $route->id,
            'student_id' => $student->id,
            'sort_order' => 0,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'reason' => 'travel',
        ])
            ->assertStatus(201);

        $list = $this->getJson('/api/absences?student_id='.$student->id)->assertOk()->json('data');
        $this->assertSame(1, $list['pagination']['total']);
        $id = $list['items'][0]['id'];

        $this->getJson('/api/absences/'.$id)->assertOk()
            ->assertJsonPath('data.reason', 'travel')
            ->assertJsonPath('data.reason_label_en', 'Travel')
            ->assertJsonPath('data.driver_id', $driver->id);
    }

    public function test_v1_notifications_unread_and_mark_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'A',
            'body' => 'One',
        ]);
        $n2 = InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'B',
            'body' => 'Two',
        ]);

        $this->getJson('/api/in-app-notifications?unread_only=1')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonCount(2, 'data.items');

        $this->postJson('/api/in-app-notifications/read', ['ids' => [$n2->id]])
            ->assertOk();

        $this->getJson('/api/in-app-notifications?unread_only=1')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.items');

        $this->deleteJson('/api/in-app-notifications/'.$n2->id)->assertOk();
        $this->assertDatabaseMissing('in_app_notifications', ['id' => $n2->id]);
    }

    public function test_v1_parent_cannot_access_other_guardians_student(): void
    {
        $school = $this->makeSchool('Shared School');
        $g1 = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G1',
            'phone' => '7300000061',
            'status' => 'active',
        ]);
        $g2 = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G2',
            'phone' => '7300000062',
            'status' => 'active',
        ]);
        $s2 = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $g2->id,
            'full_name' => 'Other Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000062',
            'guardian_name' => $g2->full_name,
            'guardian_primary_phone' => $g2->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $g1->id, 'school_id' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/students/'.$s2->id)->assertStatus(403);
        $this->putJson('/api/students/'.$s2->id, ['full_name' => 'Hack'])->assertStatus(403);
        $this->deleteJson('/api/students/'.$s2->id)->assertStatus(403);
    }

    public function test_v1_student_update_and_delete(): void
    {
        $school = $this->makeSchool('Crud Student School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Crud',
            'phone' => '7300000063',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Before',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000063',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $staff = User::factory()->create([
            'phone' => '9647908122222',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($staff);

        $this->putJson('/api/students/'.$student->id, ['full_name' => 'After'])
            ->assertOk()
            ->assertJsonPath('data.fullName', 'After');

        $this->deleteJson('/api/students/'.$student->id)->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_v1_guardian_cannot_update_or_delete_student_via_parent_students_endpoint(): void
    {
        $school = $this->makeSchool('Guardian Mutate Block');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Mutate',
            'phone' => '7300000071',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000071',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => null,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($parent);

        $this->putJson('/api/students/'.$student->id, ['full_name' => 'Hack'])->assertStatus(403);
        $this->deleteJson('/api/students/'.$student->id)->assertStatus(403);
    }

    public function test_v1_absence_update_and_delete(): void
    {
        $school = $this->makeSchool('Crud Abs School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Abs2',
            'phone' => '7300000064',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000064',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647908000064']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'U',
            'grandfather_name' => 'P',
            'last_name' => 'D',
            'age' => 35,
            'id_card_number' => 'IDC-ABS-UPD',
            'license_number' => 'LIC-ABS-UPD',
            'primary_phone' => '7770000064',
            'emergency_phone' => '7770000065',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $route = \App\Models\TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route Abs Upd',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot',
            'start_latitude' => 33.3,
            'start_longitude' => 44.3,
            'status' => 'active',
        ]);
        \App\Models\TransportRouteStudent::query()->create([
            'transport_route_id' => $route->id,
            'student_id' => $student->id,
            'sort_order' => 0,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'reason' => 'other',
        ])->assertStatus(201);

        $abs = Absence::query()->firstOrFail();
        $this->patchJson('/api/absences/'.$abs->id, ['reason' => 'medical'])
            ->assertOk()
            ->assertJsonPath('data.reason', 'medical');

        $this->deleteJson('/api/absences/'.$abs->id)->assertOk();
        $this->assertDatabaseMissing('absences', ['id' => $abs->id]);
    }

    public function test_v1_home_location_delete(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/home-location', [
            'latitude' => 1.0,
            'longitude' => 2.0,
        ])->assertStatus(201);

        $this->deleteJson('/api/home-location')->assertOk();
        $this->getJson('/api/home-location')->assertOk()->assertJsonPath('data', null);
    }

    public function test_v1_trip_request_update_and_delete(): void
    {
        $school = $this->makeSchool('Crud TripReq School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G TR',
            'phone' => '7300000065',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S TR',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000065',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'notes' => 'First',
        ])->assertStatus(201);

        $req = TripRequest::query()->firstOrFail();
        $this->putJson('/api/trip-requests/'.$req->id, ['notes' => 'Second'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Second');

        $this->deleteJson('/api/trip-requests/'.$req->id)->assertOk();
        $this->assertDatabaseMissing('trip_requests', ['id' => $req->id]);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
        ])->assertStatus(201);
        $req2 = TripRequest::query()->latest('id')->firstOrFail();
        $this->postJson('/api/trip-requests/'.$req2->id.'/cancel')->assertOk();
        $this->deleteJson('/api/trip-requests/'.$req2->id)->assertStatus(422);
    }

    public function test_trip_request_put_returns_forbidden_for_unrelated_user(): void
    {
        $school = $this->makeSchool('Trip Forbidden School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Forbidden',
            'phone' => '7300000089',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Forbidden',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000089',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $owner = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $other = User::factory()->create(['school_id' => $school->id]);
        $req = TripRequest::query()->create([
            'user_id' => $owner->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($other);

        $this->putJson('/api/trip-requests/'.$req->id, [
            'notes' => 'No access',
        ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'forbidden',
                'msg' => 'forbidden',
                'data' => null,
            ]);
    }

    public function test_v1_driver_can_list_and_accept_assigned_trip_request(): void
    {
        $school = $this->makeSchool('Driver Req School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Driver Req',
            'phone' => '7300000078',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Driver Req',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000078',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647909000078']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'Trip',
            'grandfather_name' => 'Req',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-DRQ-1',
            'license_number' => 'LIC-DRQ-1',
            'primary_phone' => '7770000078',
            'emergency_phone' => '7770001078',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $req = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'notes' => 'Assigned to driver',
        ]);

        Sanctum::actingAs($driverUser);

        $this->getJson('/api/trip-requests')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $req->id);

        $this->putJson('/api/trip-requests/'.$req->id, [
            'status' => 'accepted',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $accepted = $req->fresh();
        $this->assertNotNull($accepted->trip_history_id);
        $this->assertDatabaseHas('trip_histories', ['id' => $accepted->trip_history_id]);

        $req2 = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'notes' => 'Assigned to driver again',
        ]);

        $this->putJson('/api/trip-requests/'.$req2->id, ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $fresh = $req2->fresh();
        $this->assertNotNull($fresh->trip_history_id);
        $this->assertDatabaseHas('trip_histories', ['id' => $fresh->trip_history_id]);
    }

    public function test_v1_profile_delete(): void
    {
        $user = User::factory()->create(['name' => 'To Delete']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/profile')->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_v1_transport_lines_drivers_lists_active_drivers_for_guardian_school(): void
    {
        $school = $this->makeSchool('Transport Lines School');
        $school->update(['latitude' => 33.3152, 'longitude' => 44.3661]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G TL',
            'phone' => '7300000099',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student TL',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000099',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
        $student->update(['latitude' => 33.325, 'longitude' => 44.376]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $driverUser = User::factory()->create([
            'phone' => '9647909000999',
            'name' => 'Captain Test Driver',
            'rate' => 4.8,
            'votes' => 140,
        ]);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'X',
            'father_name' => 'Y',
            'grandfather_name' => 'Z',
            'last_name' => 'W',
            'age' => 35,
            'id_card_number' => 'IDC-TL',
            'license_number' => 'LIC-TL',
            'primary_phone' => '7770999901',
            'emergency_phone' => '7770999902',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
            'monthly_subscription_price' => 65000,
        ]);

        \App\Models\TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Route 15 - Northern Complex',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Northern depot',
            'start_latitude' => 33.324,
            'start_longitude' => 44.375,
            'status' => 'active',
        ]);

        $inactiveDriverUser = User::factory()->create(['phone' => '9647909000997']);
        $inactiveDriver = Driver::query()->create([
            'user_id' => $inactiveDriverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Off',
            'father_name' => 'Duty',
            'grandfather_name' => 'X',
            'last_name' => 'Y',
            'age' => 40,
            'id_card_number' => 'IDC-OFF',
            'license_number' => 'LIC-OFF',
            'primary_phone' => '7770999903',
            'emergency_phone' => '7770999904',
            'residential_address' => 'Addr',
            'status' => 'inactive',
        ]);

        $busOwner = User::factory()->create(['phone' => '9647909000998']);
        Bus::query()->create([
            'user_id' => $busOwner->id,
            'driver_id' => $driver->id,
            'name' => 'Bus TL',
            'number' => 'TL-100',
            'type' => 'Bus (Toyota)',
            'vehicle_model_year' => 2018,
            'ac_status' => 'yes',
            'city' => 'Baghdad',
            'capacity' => 14,
            'color' => 'white',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'TL-100',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'route_title' => 'Baghdad / Karkh / Al-Jami\'a',
            'location' => 'Campus',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'PRESENT',
            'students_preview' => null,
        ]);

        $trip = TripHistory::query()->where('driver_id', $driver->id)->first();

        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'notes' => 'Hold seat',
        ]);

        Sanctum::actingAs($parent);

        $listWithoutStudentId = $this->getJson('/api/transport-lines/drivers')
            ->assertOk()
            ->assertJsonPath('data.schoolIds.0', (string) $school->id)
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.schoolId', (string) $school->id)
            ->assertJsonPath('data.drivers.0.driverId', (string) $driver->id)
            ->assertJsonPath('data.drivers.0.driverName', 'X Y Z W')
            ->assertJsonPath('data.drivers.0.routeDescription', 'Baghdad / Karkh / Al-Jami\'a')
            ->assertJsonPath('data.drivers.0.route.tripId', (string) $trip->id)
            ->assertJsonPath('data.drivers.0.route.routeId', (string) $trip->id)
            ->assertJsonPath('data.drivers.0.route.name', 'Baghdad / Karkh / Al-Jami\'a')
            ->assertJsonPath('data.drivers.0.ratingAvg', 4.8)
            ->assertJsonPath('data.drivers.0.ratingCount', 140)
            ->assertJsonPath('data.drivers.0.vehicleType', 'Bus (Toyota)')
            ->assertJsonPath('data.drivers.0.totalSeats', 14)
            ->assertJsonPath('data.drivers.0.availableSeats', 13)
            ->assertJsonPath('data.drivers.0.plateNumber', 'TL-100')
            ->assertJsonPath('data.drivers.0.currency', 'IQD')
            ->assertJsonPath('data.drivers.0.monthlyPrice', 65000)
            ->assertJsonPath('data.drivers.0.vehicleModelYear', 2018)
            ->assertJsonPath('data.drivers.0.acStatus', 'yes');

        $distanceAutoFromOwnedStudent = $listWithoutStudentId->json('data.drivers.0.distanceKm');
        $this->assertIsNumeric($distanceAutoFromOwnedStudent);
        $this->assertGreaterThan(0, (float) $distanceAutoFromOwnedStudent);

        $this->assertNotContains(
            (string) $inactiveDriver->id,
            collect($this->getJson('/api/transport-lines/drivers')->json('data.drivers'))->pluck('driverId')->all()
        );

        $distanceFromStudent = $this->getJson('/api/transport-lines/drivers?student_id='.$student->id)
            ->assertOk()
            ->json('data.drivers.0.distanceKm');
        $this->assertIsNumeric($distanceFromStudent);
        $this->assertGreaterThan(0, (float) $distanceFromStudent);

        $distanceOverride = $this->getJson('/api/transport-lines/drivers?latitude=33.30&longitude=44.35')
            ->assertOk()
            ->json('data.drivers.0.distanceKm');
        $this->assertIsNumeric($distanceOverride);

        $this->getJson('/api/transport-lines/drivers/'.$driver->id.'?student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.driver.driverId', (string) $driver->id)
            ->assertJsonPath('data.driver.schoolId', (string) $school->id)
            ->assertJsonPath('data.driver.driverName', 'X Y Z W');

        $showAutoKm = $this->getJson('/api/transport-lines/drivers/'.$driver->id)
            ->assertOk()
            ->json('data.driver.distanceKm');
        $this->assertIsNumeric($showAutoKm);
        $this->assertGreaterThan(0, (float) $showAutoKm);
    }

    public function test_v1_transport_lines_drivers_admin_requires_school_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'school_id' => null]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/transport-lines/drivers')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_v1_transport_lines_drivers_supports_search_filters_and_pagination(): void
    {
        $school = $this->makeSchool('Transport Search School');
        $school->update(['latitude' => 33.31, 'longitude' => 44.36]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Search',
            'phone' => '7300000199',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student Search',
            'gender' => 'male',
            'grade' => '3',
            'student_phone' => '7400000199',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'latitude' => 33.32,
            'longitude' => 44.37,
            'shift_period' => 'MORNING',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $userA = User::factory()->create(['name' => 'Captain Alpha']);
        $driverA = Driver::query()->create([
            'user_id' => $userA->id,
            'school_id' => $school->id,
            'first_name' => 'Alpha',
            'father_name' => 'A',
            'grandfather_name' => 'A',
            'last_name' => 'One',
            'age' => 31,
            'id_card_number' => 'IDC-SA',
            'license_number' => 'LIC-SA',
            'primary_phone' => '7771000001',
            'emergency_phone' => '7771001001',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
            'monthly_subscription_price' => 70000,
        ]);

        $userB = User::factory()->create(['name' => 'Captain Beta']);
        $driverB = Driver::query()->create([
            'user_id' => $userB->id,
            'school_id' => $school->id,
            'first_name' => 'Beta',
            'father_name' => 'B',
            'grandfather_name' => 'B',
            'last_name' => 'Two',
            'age' => 32,
            'id_card_number' => 'IDC-SB',
            'license_number' => 'LIC-SB',
            'primary_phone' => '7771000002',
            'emergency_phone' => '7771001002',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
            'monthly_subscription_price' => 50000,
        ]);

        foreach ([$driverA, $driverB] as $routeDriver) {
            \App\Models\TransportRoute::query()->create([
                'school_id' => $school->id,
                'driver_id' => $routeDriver->id,
                'name' => 'Search route '.$routeDriver->id,
                'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
                'shift_period' => 'MORNING',
                'start_address' => 'Depot',
                'start_latitude' => 33.321,
                'start_longitude' => 44.369,
                'status' => 'active',
            ]);
        }

        Sanctum::actingAs($parent);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id.'&search=Alpha&min_monthly_price=60000&has_monthly_price=1&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.driverId', (string) $driverA->id)
            ->assertJsonPath('data.drivers.0.monthlyPrice', 70000);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id.'&max_monthly_price=55000')
            ->assertOk()
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.driverId', (string) $driverB->id);
    }

    public function test_v1_orders_list_and_driver_updates_status(): void
    {
        $school = $this->makeSchool('Orders School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Ord',
            'phone' => '7300000299',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'ليام عبدالله القحطاني',
            'gender' => 'female',
            'grade' => 'الصف الاول الاعدادى',
            'student_phone' => '7400000299',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'حي المنصور',
            'nearest_landmark' => 'شارع 14',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647909000299']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Ord',
            'age' => 35,
            'id_card_number' => 'IDC-ORD',
            'license_number' => 'LIC-ORD',
            'primary_phone' => '7770000299',
            'emergency_phone' => '7770001299',
            'residential_address' => 'Addr',
            'status' => 'active',
            'monthly_subscription_price' => 50000,
        ]);
        Bus::query()->create([
            'user_id' => User::factory()->create(['phone' => '9647909000298'])->id,
            'driver_id' => $driver->id,
            'name' => 'Bus Ord',
            'number' => 'ORD-1',
            'type' => 'Bus',
            'city' => 'Baghdad',
            'capacity' => 20,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        Sanctum::actingAs($parent);
        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'present_type' => 'مسائي',
        ])
            ->assertCreated();

        $tripRequest = TripRequest::query()->firstOrFail();

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('msg', 'Retrieve Orders Successfully')
            ->assertJsonPath('data.0.id', $tripRequest->id)
            ->assertJsonPath('data.0.student.presentType', 'مسائي')
            ->assertJsonPath('data.0.student.subscribePrice', 50000);

        Sanctum::actingAs($driverUser);
        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.total_seats', 20)
            ->assertJsonCount(1, 'data.orders');

        $this->putJson('/api/orders/'.$tripRequest->id, ['status' => 'accepted', 'order_id' => (string) $tripRequest->id])
            ->assertOk()
            ->assertJsonPath('msg', 'Order accepted successfully')
            ->assertJsonPath('data.id', $tripRequest->id)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('trip_requests', [
            'id' => $tripRequest->id,
            'status' => 'accepted',
        ]);
        $this->assertNotNull($tripRequest->fresh()->trip_history_id);
    }

    public function test_v1_trip_request_auto_assigns_driver_by_shift_period(): void
    {
        $school = $this->makeSchool('Shift School');
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Shift',
            'phone' => '7300000399',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Shift Student',
            'gender' => 'female',
            'grade' => 'G1',
            'student_phone' => '7400000399',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'mother',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $morningUser = User::factory()->create(['phone' => '9647909000391']);
        $morningDriver = Driver::query()->create([
            'user_id' => $morningUser->id,
            'school_id' => $school->id,
            'first_name' => 'M',
            'father_name' => 'M',
            'grandfather_name' => 'M',
            'last_name' => 'Driver',
            'age' => 33,
            'id_card_number' => 'IDC-SH-1',
            'license_number' => 'LIC-SH-1',
            'primary_phone' => '7770000391',
            'emergency_phone' => '7770001391',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $eveningUser = User::factory()->create(['phone' => '9647909000392']);
        $eveningDriver = Driver::query()->create([
            'user_id' => $eveningUser->id,
            'school_id' => $school->id,
            'first_name' => 'E',
            'father_name' => 'E',
            'grandfather_name' => 'E',
            'last_name' => 'Driver',
            'age' => 34,
            'id_card_number' => 'IDC-SH-2',
            'license_number' => 'LIC-SH-2',
            'primary_phone' => '7770000392',
            'emergency_phone' => '7770001392',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'EVENING',
        ]);

        Sanctum::actingAs($parent);
        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'present_type' => 'مسائي',
        ])
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $eveningDriver->id);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $morningDriver->id,
            'present_type' => 'مسائي',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.driver_id.0', 'shift_mismatch');
    }

    public function test_v1_transport_lines_route_description_uses_driver_without_trip_history(): void
    {
        $school = $this->makeSchool('Driver Route School');
        $school->update(['latitude' => 33.3152, 'longitude' => 44.3661]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G DRVRT',
            'phone' => '7300000299',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S DRVRT',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000299',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
        $student->update(['latitude' => 33.325, 'longitude' => 44.376]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647909000291', 'name' => 'Route Driver']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'R',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'W',
            'age' => 35,
            'id_card_number' => 'IDC-DRVRT',
            'license_number' => 'LIC-DRVRT',
            'primary_phone' => '7770000291',
            'emergency_phone' => '7770000292',
            'residential_address' => 'Addr',
            'route_description' => 'Morning — Sector 9 stop',
            'status' => 'active',
            'shift_period' => 'MORNING',
            'monthly_subscription_price' => 50000,
        ]);

        \App\Models\TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'name' => 'Morning — Sector 9 stop',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Sector 9 stop',
            'start_latitude' => 33.324,
            'start_longitude' => 44.375,
            'status' => 'active',
        ]);

        $busUser = User::factory()->create(['phone' => '9647909000290']);
        Bus::query()->create([
            'user_id' => $busUser->id,
            'driver_id' => $driver->id,
            'name' => 'Bus Label Fallback',
            'number' => 'DRV-RT-1',
            'type' => 'Van',
            'city' => 'Baghdad',
            'capacity' => 10,
            'color' => 'white',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        Sanctum::actingAs($parent);
        $this->getJson('/api/transport-lines/drivers?school_id='.$school->id.'&student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.drivers.0.routeDescription', 'Morning — Sector 9 stop — Sector 9 stop');

        $driver->update(['route_description' => null]);
        $this->getJson('/api/transport-lines/drivers?school_id='.$school->id.'&student_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.drivers.0.routeDescription', 'Morning — Sector 9 stop — Sector 9 stop');
    }

    public function test_v1_transport_lines_drivers_matches_student_to_transport_route_corridor(): void
    {
        $school = $this->makeSchool('Route Match School');
        $school->update(['latitude' => 33.31, 'longitude' => 44.36, 'address' => 'School Campus']);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Route',
            'phone' => '7300000399',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student Route',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000399',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => 33.29,
            'longitude' => 44.10,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $matchingUser = User::factory()->create(['phone' => '9647909000391', 'name' => 'Matching Driver']);
        $matchingDriver = Driver::query()->create([
            'user_id' => $matchingUser->id,
            'school_id' => $school->id,
            'first_name' => 'Match',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'W',
            'age' => 35,
            'id_card_number' => 'IDC-MATCH',
            'license_number' => 'LIC-MATCH',
            'primary_phone' => '7770000391',
            'emergency_phone' => '7770000392',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);
        $otherUser = User::factory()->create(['phone' => '9647909000392', 'name' => 'Other Driver']);
        $otherDriver = Driver::query()->create([
            'user_id' => $otherUser->id,
            'school_id' => $school->id,
            'first_name' => 'Other',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'W',
            'age' => 36,
            'id_card_number' => 'IDC-OTHER',
            'license_number' => 'LIC-OTHER',
            'primary_phone' => '7770000393',
            'emergency_phone' => '7770000394',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $matchingDriver->id,
            'name' => 'Bus M',
            'number' => 'M-1',
            'type' => 'Van',
            'city' => 'Baghdad',
            'capacity' => 12,
            'color' => 'white',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);
        Bus::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $otherDriver->id,
            'name' => 'Bus O',
            'number' => 'O-1',
            'type' => 'Van',
            'city' => 'Baghdad',
            'capacity' => 12,
            'color' => 'white',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        \App\Models\TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $matchingDriver->id,
            'name' => 'Morning Route A',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Depot A',
            'start_latitude' => 33.291,
            'start_longitude' => 44.105,
            'status' => 'active',
        ]);
        \App\Models\TransportRoute::query()->create([
            'school_id' => $school->id,
            'driver_id' => $otherDriver->id,
            'name' => 'Morning Route B',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'shift_period' => 'MORNING',
            'start_address' => 'Far depot',
            'start_latitude' => 33.0,
            'start_longitude' => 44.0,
            'status' => 'active',
        ]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.driverId', (string) $matchingDriver->id)
            ->assertJsonPath('data.drivers.0.matchesStudentRoute', true)
            ->assertJsonPath('data.drivers.0.route.tripType', 'MORNING_PICKUP')
            ->assertJsonPath('data.drivers.0.route.startAddress', 'Depot A');

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id.'&trip_type=MORNING_PICKUP&matches_route_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.driverId', (string) $matchingDriver->id);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id.'&matches_route_only=0')
            ->assertOk()
            ->assertJsonCount(2, 'data.drivers');
    }

    public function test_v1_transport_lines_drivers_matches_student_to_driver_service_areas(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Service Area School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'latitude' => 33.31,
            'longitude' => 44.36,
            'status' => 'active',
        ]);

        $governorate = District::query()->create(['name' => 'Baghdad', 'sort_order' => 1]);
        $area = Area::query()->create(['district_id' => $governorate->id, 'name' => 'Karkh', 'sort_order' => 0]);
        $nearNeighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Near stop',
            'sort_order' => 0,
            'latitude' => 33.311,
            'longitude' => 44.361,
        ]);
        $farNeighborhood = Neighborhood::query()->create([
            'area_id' => $area->id,
            'name' => 'Far stop',
            'sort_order' => 1,
            'latitude' => 33.0,
            'longitude' => 44.0,
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G SA',
            'phone' => '7300000499',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Near Student',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000499',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'latitude' => 33.312,
            'longitude' => 44.362,
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $matchingDriver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'Service',
            'father_name' => 'Area',
            'grandfather_name' => 'X',
            'last_name' => 'Driver',
            'age' => 35,
            'id_card_number' => 'IDC-SA-M',
            'license_number' => 'LIC-SA-M',
            'primary_phone' => '7770000491',
            'emergency_phone' => '7770000492',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
            'monthly_subscription_price' => 60000,
        ]);
        $otherDriver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'Far',
            'father_name' => 'Area',
            'grandfather_name' => 'X',
            'last_name' => 'Driver',
            'age' => 36,
            'id_card_number' => 'IDC-SA-O',
            'license_number' => 'LIC-SA-O',
            'primary_phone' => '7770000493',
            'emergency_phone' => '7770000494',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $matchingDriver->id,
            'name' => 'Bus SA-M',
            'number' => 'SA-M',
            'type' => 'Van',
            'city' => 'Baghdad',
            'capacity' => 12,
            'color' => 'white',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);
        Bus::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $otherDriver->id,
            'name' => 'Bus SA-O',
            'number' => 'SA-O',
            'type' => 'Van',
            'city' => 'Baghdad',
            'capacity' => 12,
            'color' => 'white',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $matchingServiceArea = DriverServiceArea::query()->create([
            'driver_id' => $matchingDriver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'monthly_subscription_price' => 60000,
            'sort_order' => 0,
        ]);
        $matchingServiceArea->neighborhoods()->attach($nearNeighborhood->id);

        $otherServiceArea = DriverServiceArea::query()->create([
            'driver_id' => $otherDriver->id,
            'district_id' => $governorate->id,
            'area_id' => $area->id,
            'sort_order' => 0,
        ]);
        $otherServiceArea->neighborhoods()->attach($farNeighborhood->id);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $matchingDriver->id,
            'bus_number' => 'SA-M',
            'trip_type' => \App\Enums\TripType::MORNING_PICKUP->value,
            'route_title' => 'Baghdad / Karkh / Near stop',
            'start_address' => 'Near stop',
            'start_latitude' => 33.311,
            'start_longitude' => 44.361,
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->subHour(),
            'status' => 'PRESENT',
        ]);

        $trip = TripHistory::query()->where('driver_id', $matchingDriver->id)->first();

        Sanctum::actingAs($parent);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.driverId', (string) $matchingDriver->id)
            ->assertJsonPath('data.drivers.0.matchesStudentRoute', true)
            ->assertJsonPath('data.drivers.0.routeDescription', 'Baghdad / Karkh / Near stop')
            ->assertJsonPath('data.drivers.0.route.tripId', (string) $trip->id)
            ->assertJsonPath('data.drivers.0.route.routeId', (string) $trip->id)
            ->assertJsonPath('data.drivers.0.route.name', 'Baghdad / Karkh / Near stop')
            ->assertJsonPath('data.drivers.0.route.startAddress', 'Near stop')
            ->assertJsonPath('data.drivers.0.route.startLatitude', 33.311);

        $this->getJson('/api/transport-lines/drivers?student_id='.$student->id.'&matches_route_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.drivers')
            ->assertJsonPath('data.drivers.0.driverId', (string) $matchingDriver->id);
    }

    private function makeSchool(string $nameEn): School
    {
        return School::query()->create([
            'name_ar' => $nameEn,
            'name_en' => $nameEn,
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
    }
}
