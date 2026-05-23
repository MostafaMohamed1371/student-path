<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\DelayAlert;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\SosAlert;
use App\Models\Student;
use App\Models\SupportComplaint;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebDashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_and_notifications_redirect_guest_to_login(): void
    {
        $this->get(route('dashboard.payments'))->assertRedirect(route('login'));
        $this->get(route('dashboard.notifications.hub'))->assertRedirect(route('login'));
        $this->get(route('dashboard.in_app_notifications'))->assertRedirect(route('login'));
        $this->get(route('dashboard.delay_alerts'))->assertRedirect(route('login'));
        $this->get(route('dashboard.sos_alerts'))->assertRedirect(route('login'));
        $this->get(route('dashboard.trip_finalization_reports'))->assertRedirect(route('login'));
        $this->get(route('dashboard.trip_requests.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.absences.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.support_complaints.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.trip_requests.show', 1))->assertRedirect(route('login'));
        $this->get(route('dashboard.absences.create'))->assertRedirect(route('login'));
        $this->get(route('dashboard.support_complaints.create'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_payments_and_notifications_for_users(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['school_id' => $school->id]);
        $wallet = Wallet::query()->create([
            'user_id' => $parent->id,
            'balance' => 100,
            'currency' => 'IQD',
        ]);
        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'recharge',
            'amount' => 50,
            'balance_after' => 100,
            'meta' => ['gateway' => 'test'],
        ]);

        InAppNotification::query()->create([
            'user_id' => $parent->id,
            'title' => 'Hello',
            'body' => 'Body text',
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'school_id' => $school->id]);
        $this->actingAs($admin);

        $this->get(route('dashboard.payments'))
            ->assertOk()
            ->assertSee('50.00', false)
            ->assertSee((string) $parent->phone, false);

        $this->get(route('dashboard.in_app_notifications'))
            ->assertOk()
            ->assertSee('Hello', false)
            ->assertSee('Body text', false);

        $driverUser = User::factory()->create(['phone' => '9647909002010']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'A',
            'age' => 30,
            'id_card_number' => 'IDC-DA-2010',
            'license_number' => 'LIC-DA-2010',
            'primary_phone' => '7770002010',
            'emergency_phone' => '7770003010',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B',
            'route_title' => 'T',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'ACTIVE',
        ]);
        DelayAlert::query()->create([
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'reason_type' => 'TRAFFIC',
            'delay_duration_minutes' => 10,
            'note' => 'Traffic jam',
            'driver_lat' => 33.3,
            'driver_lng' => 44.3,
        ]);

        $this->get(route('dashboard.delay_alerts'))
            ->assertOk()
            ->assertSee('TRAFFIC', false)
            ->assertSee('10', false);

        SosAlert::query()->create([
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'emergency_type' => 'SOS',
            'status' => 'TRIGGERED',
            'driver_lat' => 33.3128,
            'driver_lng' => 44.3615,
            'triggered_at' => now(),
        ]);

        $this->get(route('dashboard.sos_alerts'))
            ->assertOk()
            ->assertSee('SOS', false)
            ->assertSee('TRIGGERED', false);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B2',
            'route_title' => 'Finalized Trip',
            'location' => 'L2',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now()->subHour(),
            'end_time' => now()->subMinutes(5),
            'final_lat' => 33.3123,
            'final_lng' => 44.3610,
            'note' => 'تمت الرحلة بنجاح',
            'status' => 'COMPLETED',
        ]);

        $this->get(route('dashboard.trip_finalization_reports'))
            ->assertOk()
            ->assertSee('TRP-', false)
            ->assertSee('COMPLETED', false)
            ->assertSee('33.3123, 44.361', false)
            ->assertSee('تمت الرحلة بنجاح', false);
    }

    public function test_staff_sees_only_school_scoped_payment_rows(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A', 'name_en' => 'School A', 'province' => 'P', 'district' => '1', 'address' => 'A', 'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B', 'name_en' => 'School B', 'province' => 'P', 'district' => '2', 'address' => 'B', 'status' => 'active',
        ]);

        $userA = User::factory()->create(['school_id' => $schoolA->id]);
        $userB = User::factory()->create(['school_id' => $schoolB->id]);

        $wA = Wallet::query()->create(['user_id' => $userA->id, 'balance' => 10, 'currency' => 'IQD']);
        $wB = Wallet::query()->create(['user_id' => $userB->id, 'balance' => 20, 'currency' => 'IQD']);

        WalletTransaction::query()->create([
            'wallet_id' => $wA->id, 'type' => 'recharge', 'amount' => 10, 'balance_after' => 10, 'meta' => null,
        ]);
        WalletTransaction::query()->create([
            'wallet_id' => $wB->id, 'type' => 'recharge', 'amount' => 99, 'balance_after' => 20, 'meta' => null,
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $schoolA->id]);
        $this->actingAs($staff);

        $html = $this->get(route('dashboard.payments'))->assertOk()->getContent();
        $this->assertStringContainsString('10.00', $html);
        $this->assertStringNotContainsString('99.00', $html);
    }

    public function test_admin_can_update_support_complaint_status(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['school_id' => $school->id]);
        $complaint = SupportComplaint::query()->create([
            'user_id' => $parent->id,
            'category_id' => '1',
            'details' => 'Details text',
            'attachments' => null,
            'complaint_number' => '#CMP-2026-0001',
            'status' => 'RECEIVED',
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'school_id' => $school->id]);
        $this->actingAs($admin);

        $this->put(route('dashboard.support_complaints.update', $complaint), [
            'category_id' => '1',
            'details' => $complaint->details,
            'status' => 'IN_REVIEW',
        ])->assertRedirect(route('dashboard.support_complaints.show', $complaint));

        $this->assertSame('IN_REVIEW', $complaint->fresh()->status);
    }

    public function test_staff_can_accept_trip_request_in_school_scope_and_create_trip(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School TR',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G TR',
            'phone' => '7300000099',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S TR',
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
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $req = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'trip_history_id' => null,
            'status' => 'pending',
            'notes' => 'Need a seat',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff);

        $this->put(route('dashboard.trip_requests.update_status', $req), [
            'status' => 'accepted',
        ])->assertRedirect(route('dashboard.trip_requests.show', $req));

        $fresh = $req->fresh();
        $this->assertSame('accepted', $fresh->status);
        $this->assertNotNull($fresh->trip_history_id);
        $this->assertDatabaseHas('trip_histories', ['id' => $fresh->trip_history_id]);
        $this->assertSame(1, TripHistory::query()->whereKey($fresh->trip_history_id)->value('students_count'));
    }

    public function test_dashboard_student_create_does_not_auto_create_trip_request(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School D',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian D',
            'phone' => '7300000069',
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'school_id' => $school->id]);
        $this->actingAs($admin);

        $this->post(route('dashboard.students.store'), [
            'school_id' => $school->id,
            'full_name' => 'Student D',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7900000069',
            'guardian_id' => $guardian->id,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ])->assertRedirect(route('dashboard.students.index'));

        $studentId = Student::query()->where('full_name', 'Student D')->value('id');
        $this->assertNotNull($studentId);
        $this->assertDatabaseMissing('trip_requests', [
            'student_id' => $studentId,
        ]);
    }

    public function test_driver_sees_only_assigned_trip_requests_and_can_update_status(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School Driver Dashboard',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Driver',
            'phone' => '7300000082',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Driver',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000082',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647909000082']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Dash',
            'father_name' => 'Driver',
            'grandfather_name' => 'User',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-DASH-82',
            'license_number' => 'LIC-DASH-82',
            'primary_phone' => '7770000082',
            'emergency_phone' => '7770001082',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $otherDriverUser = User::factory()->create(['phone' => '9647909000083']);
        $otherDriver = Driver::query()->create([
            'user_id' => $otherDriverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Other',
            'father_name' => 'Driver',
            'grandfather_name' => 'User',
            'last_name' => 'Two',
            'age' => 31,
            'id_card_number' => 'IDC-DASH-83',
            'license_number' => 'LIC-DASH-83',
            'primary_phone' => '7770000083',
            'emergency_phone' => '7770001083',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $visibleReq = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
            'notes' => 'Mine',
        ]);
        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $otherDriver->id,
            'status' => 'pending',
            'notes' => 'Not mine',
        ]);

        $this->actingAs($driverUser);
        $this->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Not mine');

        $this->put(route('dashboard.trip_requests.update_status', $visibleReq), [
            'status' => 'rejected',
        ])->assertRedirect(route('dashboard.trip_requests.show', $visibleReq));

        $this->assertSame('rejected', $visibleReq->fresh()->status);
    }

    public function test_dashboard_trip_request_shows_parent_guardian_name_not_stale_user_name(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School Parent Name',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Fatima Hassan Karim',
            'phone' => '7300000299',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child Parent Name',
            'gender' => 'female',
            'grade' => '1',
            'student_phone' => '7400000299',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'mother',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
            'name' => 'Ahmed Ali Samy',
            'phone' => '9647300000299',
        ]);
        $driver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'Test',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-DPN',
            'license_number' => 'LIC-DPN',
            'primary_phone' => '7770000299',
            'emergency_phone' => '7770001299',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
            'notes' => 'Parent name display test',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('Fatima Hassan Karim', false)
            ->assertDontSee('Ahmed Ali Samy', false);
    }

    public function test_parent_api_trip_request_appears_on_dashboard_trip_requests_list(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School Parent API',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Parent API',
            'phone' => '7300000199',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'S Parent API',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000199',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $parent = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $driverUser = User::factory()->create(['phone' => '9647909000199']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'API',
            'father_name' => 'Driver',
            'grandfather_name' => 'Test',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-API-99',
            'license_number' => 'LIC-API-99',
            'primary_phone' => '7770000199',
            'emergency_phone' => '7770001199',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        Sanctum::actingAs($parent);
        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'notes' => 'Parent app booking',
        ])->assertStatus(201);

        $req = TripRequest::query()->firstOrFail();
        $this->assertSame('pending', $req->status);
        $this->assertSame($driver->id, (int) $req->driver_id);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);
        $this->actingAs($staff)
            ->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('Parent app booking')
            ->assertSee((string) $req->id);

        $this->actingAs($driverUser)
            ->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('Parent app booking');
    }

    public function test_driver_dashboard_shows_active_sos_card_when_exists(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School SOS Home',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $driverUser = User::factory()->create(['phone' => '9647909000311', 'school_id' => $school->id]);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'SOS',
            'father_name' => 'Driver',
            'grandfather_name' => 'Home',
            'last_name' => 'Card',
            'age' => 31,
            'id_card_number' => 'IDC-HOME-SOS',
            'license_number' => 'LIC-HOME-SOS',
            'primary_phone' => '7770000311',
            'emergency_phone' => '7770001311',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-SOS-H',
            'route_title' => 'SOS HOME',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);
        $sos = SosAlert::query()->create([
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'emergency_type' => 'SOS',
            'status' => 'TRIGGERED',
            'driver_lat' => 33.3128,
            'driver_lng' => 44.3615,
            'triggered_at' => now(),
        ]);

        $this->actingAs($driverUser);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.driver_active_sos_title'), false)
            ->assertSee('SOS-'.$sos->id, false)
            ->assertSee('TRP-'.$trip->id, false);
    }

    public function test_admin_linked_to_driver_still_sees_all_trip_requests(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School Admin Driver',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $adminUser = User::factory()->create(['is_admin' => true]);
        $otherDriver = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $school->id,
            'first_name' => 'Assigned',
            'father_name' => 'Driver',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-AD1',
            'license_number' => 'LIC-AD1',
            'primary_phone' => '7770000401',
            'emergency_phone' => '7770001401',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        Driver::query()->create([
            'user_id' => $adminUser->id,
            'school_id' => $school->id,
            'first_name' => 'Admin',
            'father_name' => 'Also',
            'grandfather_name' => 'Driver',
            'last_name' => 'Row',
            'age' => 40,
            'id_card_number' => 'IDC-AD2',
            'license_number' => 'LIC-AD2',
            'primary_phone' => '7770000402',
            'emergency_phone' => '7770001402',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['school_id' => $school->id]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Student Admin Driver',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000401',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000401',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $otherDriver->id,
            'status' => 'pending',
            'notes' => 'REQ-ADMIN-DRIVER-SCOPE',
        ]);

        $this->actingAs($adminUser)
            ->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('REQ-ADMIN-DRIVER-SCOPE', false);
    }

    public function test_trips_and_buses_index_support_school_driver_filters(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School Trips A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School Trips B',
            'province' => 'P',
            'district' => '2',
            'address' => 'B',
            'status' => 'active',
        ]);

        $driverA = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $schoolA->id,
            'first_name' => 'Trip',
            'father_name' => 'Driver',
            'grandfather_name' => 'A',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-TR-A',
            'license_number' => 'LIC-TR-A',
            'primary_phone' => '7770000501',
            'emergency_phone' => '7770001501',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $driverB = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $schoolB->id,
            'first_name' => 'Trip',
            'father_name' => 'Driver',
            'grandfather_name' => 'B',
            'last_name' => 'Two',
            'age' => 31,
            'id_card_number' => 'IDC-TR-B',
            'license_number' => 'LIC-TR-B',
            'primary_phone' => '7770000502',
            'emergency_phone' => '7770001502',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        TripHistory::query()->create([
            'school_id' => $schoolA->id,
            'driver_id' => $driverA->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'BUS-A',
            'start_time' => now(),
            'status' => 'PRESENT',
        ]);
        TripHistory::query()->create([
            'school_id' => $schoolB->id,
            'driver_id' => $driverB->id,
            'trip_type' => 'EVENING_PICKUP',
            'bus_number' => 'BUS-B',
            'start_time' => now(),
            'status' => 'PRESENT',
        ]);

        Bus::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $driverA->id,
            'name' => 'Bus A',
            'number' => '10',
            'type' => 'standard',
            'city' => 'Baghdad',
            'capacity' => 40,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);
        Bus::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $driverB->id,
            'name' => 'Bus B',
            'number' => '20',
            'type' => 'standard',
            'city' => 'Basra',
            'capacity' => 40,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.trips.index', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertSee('BUS-A', false)
            ->assertDontSee('BUS-B', false);

        $this->get(route('dashboard.trips.index', ['shift_period' => 'MORNING']))
            ->assertOk()
            ->assertSee('BUS-A', false)
            ->assertDontSee('BUS-B', false);

        $this->get(route('dashboard.buses.index', ['driver_id' => $driverA->id]))
            ->assertOk()
            ->assertSee('Bus A', false)
            ->assertDontSee('Bus B', false);
    }

    public function test_roster_indexes_support_school_driver_filters(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'Roster School A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'Roster School B',
            'province' => 'P',
            'district' => '2',
            'address' => 'B',
            'status' => 'active',
        ]);

        $driverA = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $schoolA->id,
            'first_name' => 'Roster',
            'father_name' => 'Driver',
            'grandfather_name' => 'A',
            'last_name' => 'Alpha',
            'age' => 30,
            'id_card_number' => 'IDC-ROS-A',
            'license_number' => 'LIC-ROS-A',
            'primary_phone' => '7770000601',
            'emergency_phone' => '7770001601',
            'residential_address' => 'Addr',
            'shift_period' => 'MORNING',
            'status' => 'active',
        ]);
        $driverB = Driver::query()->create([
            'user_id' => User::factory()->create()->id,
            'school_id' => $schoolB->id,
            'first_name' => 'Roster',
            'father_name' => 'Driver',
            'grandfather_name' => 'B',
            'last_name' => 'Beta',
            'age' => 31,
            'id_card_number' => 'IDC-ROS-B',
            'license_number' => 'LIC-ROS-B',
            'primary_phone' => '7770000602',
            'emergency_phone' => '7770001602',
            'residential_address' => 'Addr',
            'shift_period' => 'EVENING',
            'status' => 'active',
        ]);

        $guardianA = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Guardian Roster A',
            'phone' => '7300000601',
            'status' => 'active',
        ]);
        Guardian::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Guardian Roster B',
            'phone' => '7300000602',
            'status' => 'active',
        ]);

        $studentOnRoute = Student::query()->create([
            'school_id' => $schoolA->id,
            'guardian_id' => $guardianA->id,
            'full_name' => 'Student Roster Routed',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000601',
            'guardian_name' => $guardianA->full_name,
            'guardian_primary_phone' => $guardianA->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'shift_period' => 'MORNING',
            'status' => 'active',
        ]);
        Student::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Student Roster Evening',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000603',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000603',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'shift_period' => 'EVENING',
            'status' => 'active',
        ]);
        Student::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Student Roster Other',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000602',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000602',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $route = TransportRoute::query()->create([
            'school_id' => $schoolA->id,
            'driver_id' => $driverA->id,
            'name' => 'Route A',
            'shift_period' => 'MORNING',
            'trip_type' => 'MORNING_PICKUP',
            'start_address' => 'Start',
            'start_latitude' => 33.31,
            'start_longitude' => 44.36,
            'status' => 'active',
        ]);
        TransportRouteStudent::query()->create([
            'transport_route_id' => $route->id,
            'student_id' => $studentOnRoute->id,
            'sort_order' => 0,
            'distance_from_school_km' => 1,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.drivers.index', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertSee('Alpha', false)
            ->assertDontSee('Beta', false);

        $this->get(route('dashboard.guardians.index', ['school_id' => $schoolA->id]))
            ->assertOk()
            ->assertSee('Guardian Roster A', false)
            ->assertDontSee('Guardian Roster B', false);

        $this->get(route('dashboard.students.index', ['driver_id' => $driverA->id]))
            ->assertOk()
            ->assertSee('Student Roster Routed', false)
            ->assertDontSee('Student Roster Other', false);

        $this->get(route('dashboard.guardians.index', ['driver_id' => $driverA->id]))
            ->assertOk()
            ->assertSee('Guardian Roster A', false)
            ->assertDontSee('Guardian Roster B', false);

        $this->get(route('dashboard.schools.index', ['school_id' => $schoolB->id]))
            ->assertOk()
            ->assertSee('Roster School B', false)
            ->assertDontSee('Roster School A', false);

        $this->get(route('dashboard.schools.index', ['driver_id' => $driverA->id]))
            ->assertOk()
            ->assertSee('Roster School A', false)
            ->assertDontSee('Roster School B', false);

        $this->get(route('dashboard.schools.index', ['student_id' => $studentOnRoute->id]))
            ->assertOk()
            ->assertSee('Roster School A', false)
            ->assertDontSee('Roster School B', false);

        $this->get(route('dashboard.drivers.index', ['shift_period' => 'MORNING']))
            ->assertOk()
            ->assertSee('Alpha', false)
            ->assertDontSee('Beta', false);

        $this->get(route('dashboard.students.index', [
            'school_id' => $schoolA->id,
            'shift_period' => 'MORNING',
        ]))
            ->assertOk()
            ->assertSee('Student Roster Routed', false)
            ->assertDontSee('Student Roster Evening', false)
            ->assertDontSee('Student Roster Other', false);
    }

    public function test_users_index_supports_school_role_and_relation_filters(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Users Filter School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $viewer = User::factory()->create([
            'is_admin' => true,
            'name' => 'Dashboard Viewer',
            'school_id' => null,
        ]);

        User::factory()->create([
            'is_admin' => true,
            'name' => 'Listed Admin Target',
            'school_id' => null,
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Users Filter Guardian',
            'phone' => '7300000801',
            'status' => 'active',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'name' => 'Listed Parent Target',
            'phone' => '7300000801',
        ]);

        $driverUser = User::factory()->create([
            'school_id' => $school->id,
            'phone' => '7770000802',
            'name' => 'Listed Driver Target',
        ]);
        Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Drv',
            'father_name' => 'Filter',
            'grandfather_name' => 'D',
            'last_name' => 'One',
            'age' => 30,
            'id_card_number' => 'IDC-USR-D',
            'license_number' => 'LIC-USR-D',
            'primary_phone' => '7770000802',
            'emergency_phone' => '7770001802',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $this->actingAs($viewer);

        $this->get(route('dashboard.users.index', ['user_role' => 'admin']))
            ->assertOk()
            ->assertSee('Listed Admin Target', false)
            ->assertDontSee('Listed Parent Target', false);

        $this->get(route('dashboard.users.index', ['guardian_id' => $guardian->id]))
            ->assertOk()
            ->assertSee('Listed Parent Target', false)
            ->assertDontSee('Listed Admin Target', false);

        $this->get(route('dashboard.users.index', ['school_id' => $school->id]))
            ->assertOk()
            ->assertSee('Listed Parent Target', false)
            ->assertSee('Listed Driver Target', false);
    }

    public function test_absences_index_supports_student_and_shift_filters(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Absence School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['school_id' => $school->id]);

        $studentMorning = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Absence Student Morning',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000701',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000701',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'shift_period' => 'MORNING',
            'status' => 'active',
        ]);
        $studentEvening = Student::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Absence Student Evening',
            'gender' => 'male',
            'grade' => '2',
            'student_phone' => '7400000702',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000702',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'shift_period' => 'EVENING',
            'status' => 'active',
        ]);

        Absence::query()->create([
            'user_id' => $parent->id,
            'student_id' => $studentMorning->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'ABSENCE-MORNING-REASON',
        ]);
        Absence::query()->create([
            'user_id' => $parent->id,
            'student_id' => $studentEvening->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'ABSENCE-EVENING-REASON',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.absences.index', ['student_id' => $studentMorning->id]))
            ->assertOk()
            ->assertSee('ABSENCE-MORNING-REASON', false)
            ->assertDontSee('ABSENCE-EVENING-REASON', false);

        $this->get(route('dashboard.absences.index', ['shift_period' => 'MORNING']))
            ->assertOk()
            ->assertSee('ABSENCE-MORNING-REASON', false)
            ->assertDontSee('ABSENCE-EVENING-REASON', false);
    }

    public function test_admin_sees_all_trip_requests_and_school_staff_sees_only_their_school(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School Scope A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School Scope B',
            'province' => 'P',
            'district' => '2',
            'address' => 'B',
            'status' => 'active',
        ]);

        $parentA = User::factory()->create(['school_id' => $schoolA->id]);
        $parentB = User::factory()->create(['school_id' => $schoolB->id]);

        $studentA = Student::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Student Scope A',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000301',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000301',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $studentB = Student::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Student Scope B',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000302',
            'guardian_name' => 'G',
            'guardian_primary_phone' => '7300000302',
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        TripRequest::query()->create([
            'user_id' => $parentA->id,
            'student_id' => $studentA->id,
            'status' => 'pending',
            'notes' => 'REQ-SCOPE-A',
        ]);
        TripRequest::query()->create([
            'user_id' => $parentB->id,
            'student_id' => $studentB->id,
            'status' => 'pending',
            'notes' => 'REQ-SCOPE-B',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('REQ-SCOPE-A', false)
            ->assertSee('REQ-SCOPE-B', false);

        $staffA = User::factory()->create(['is_admin' => false, 'school_id' => $schoolA->id]);
        $this->actingAs($staffA)
            ->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('REQ-SCOPE-A', false)
            ->assertDontSee('REQ-SCOPE-B', false);
    }

    public function test_trip_requests_index_supports_pagination(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School Paginate',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G Paginate',
            'phone' => '7300000888',
            'status' => 'active',
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student Paginate',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000888',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create();
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'W',
            'age' => 30,
            'id_card_number' => 'IDC-PAG',
            'license_number' => 'LIC-PAG',
            'primary_phone' => '7770000888',
            'emergency_phone' => '7770000889',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        foreach (range(1, 26) as $i) {
            TripRequest::query()->create([
                'user_id' => $parent->id,
                'student_id' => $student->id,
                'driver_id' => $driver->id,
                'trip_history_id' => null,
                'status' => 'pending',
                'notes' => 'REQ-PAGE-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.trip_requests.index'))
            ->assertOk()
            ->assertSee('dash-pagination', false)
            ->assertSee(__('dashboard.pagination_next'), false)
            ->assertSee('REQ-PAGE-26', false)
            ->assertDontSee('REQ-PAGE-01', false);

        $this->get(route('dashboard.trip_requests.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('REQ-PAGE-01', false)
            ->assertDontSee('REQ-PAGE-26', false);
    }

    public function test_notifications_hub_and_in_app_type_filter(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School N',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['school_id' => $school->id]);
        InAppNotification::query()->create([
            'user_id' => $parent->id,
            'title' => 'Trip started title',
            'body' => 'Trip started body',
            'data' => ['type' => 'TRIP_STARTED', 'trip_id' => 'TRP-99'],
        ]);
        InAppNotification::query()->create([
            'user_id' => $parent->id,
            'title' => 'Chat ping',
            'body' => 'Chat body',
            'data' => ['type' => 'CHAT_MESSAGE', 'conversation_id' => 1],
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.notifications.hub'))
            ->assertOk()
            ->assertSee(__('dashboard.menu_notifications_hub'), false)
            ->assertSee('Trip started title', false);

        $this->get(route('dashboard.in_app_notifications', ['notification_type' => 'TRIP_STARTED']))
            ->assertOk()
            ->assertSee('Trip started title', false)
            ->assertDontSee('Chat ping', false);

        $this->get(route('dashboard.in_app_notifications', ['unread_only' => 1]))
            ->assertOk()
            ->assertSee('Trip started title', false)
            ->assertSee('Chat ping', false);
    }
}
