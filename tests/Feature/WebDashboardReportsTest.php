<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\SupportComplaint;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebDashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_and_notifications_redirect_guest_to_login(): void
    {
        $this->get(route('dashboard.payments'))->assertRedirect(route('login'));
        $this->get(route('dashboard.in_app_notifications'))->assertRedirect(route('login'));
        $this->get(route('dashboard.trip_requests.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.absences.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.support_complaints.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.trip_requests.show', 1))->assertRedirect(route('login'));
        $this->get(route('dashboard.trip_requests.create'))->assertRedirect(route('login'));
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

    public function test_staff_can_approve_trip_request_in_school_scope(): void
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
            'status' => 'approved',
        ])->assertRedirect(route('dashboard.trip_requests.show', $req));

        $this->assertSame('approved', $req->fresh()->status);
    }
}
