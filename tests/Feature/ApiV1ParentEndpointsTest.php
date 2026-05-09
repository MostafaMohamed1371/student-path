<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
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
            'place_id' => 'test_place',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.latitude', 33.3152)
            ->assertJsonPath('data.place_id', 'test_place');

        $this->getJson('/api/home-location')
            ->assertOk()
            ->assertJsonPath('data.formatted_address', 'Baghdad');
    }

    public function test_v1_district_areas(): void
    {
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

    public function test_v1_parent_can_create_student_when_guardian_linked(): void
    {
        $school = $this->makeSchool('Add Student School');
        $driverUser = User::factory()->create(['phone' => '9647909000012']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Auto',
            'father_name' => 'Assign',
            'grandfather_name' => 'Driver',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-AUTO-1',
            'license_number' => 'LIC-AUTO-1',
            'primary_phone' => '7770000012',
            'emergency_phone' => '7770001012',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian G2',
            'phone' => '7300000002',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/students', [
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
        $this->assertDatabaseHas('trip_requests', [
            'user_id' => $user->id,
            'student_id' => Student::query()->where('full_name', 'New Child')->value('id'),
            'driver_id' => $driver->id,
            'status' => 'pending',
        ]);
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
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '1',
            'start_time' => now()->addDay(),
            'end_time' => null,
            'status' => 'PRESENT',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'trip_history_id' => $trip->id,
            'notes' => 'Please',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/trip-requests')->assertOk()->assertJsonPath('data.pagination.total', 1);

        $req = TripRequest::query()->firstOrFail();
        $this->getJson('/api/trip-requests/'.$req->id)->assertOk()->assertJsonPath('data.id', $req->id);

        $this->postJson('/api/trip-requests/'.$req->id.'/cancel')->assertOk();
        $this->assertSame('cancelled', $req->fresh()->status);
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
        Sanctum::actingAs($user);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'reason' => 'Travel',
        ])
            ->assertStatus(201);

        $list = $this->getJson('/api/absences?student_id='.$student->id)->assertOk()->json('data');
        $this->assertSame(1, $list['pagination']['total']);
        $id = $list['items'][0]['id'];

        $this->getJson('/api/absences/'.$id)->assertOk()->assertJsonPath('data.reason', 'Travel');
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
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        Sanctum::actingAs($user);

        $this->putJson('/api/students/'.$student->id, ['full_name' => 'After'])
            ->assertOk()
            ->assertJsonPath('data.fullName', 'After');

        $this->deleteJson('/api/students/'.$student->id)->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
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
        Sanctum::actingAs($user);

        $this->postJson('/api/absences', [
            'student_id' => $student->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'reason' => 'Old',
        ])->assertStatus(201);

        $abs = Absence::query()->firstOrFail();
        $this->patchJson('/api/absences/'.$abs->id, ['reason' => 'New reason'])
            ->assertOk()
            ->assertJsonPath('data.reason', 'New reason');

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
