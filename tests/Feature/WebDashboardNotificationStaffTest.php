<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\School;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebDashboardNotificationStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_notification_read_and_remove_fcm_token(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['school_id' => $school->id]);
        $notification = InAppNotification::query()->create([
            'user_id' => $parent->id,
            'title' => 'Test',
            'body' => 'Body',
            'data' => ['type' => 'TRIP_STARTED'],
        ]);

        $token = UserFcmToken::query()->create([
            'user_id' => $parent->id,
            'token' => str_repeat('c', 140),
            'platform' => 'android',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.in_app_notifications.show', $notification->id))
            ->assertOk()
            ->assertSee('Test', false)
            ->assertSee('TRIP_STARTED', false);

        $this->post(route('dashboard.in_app_notifications.mark_read', $notification->id))
            ->assertRedirect(route('dashboard.in_app_notifications'))
            ->assertSessionHas('success');

        $this->assertNotNull($notification->fresh()->read_at);

        $this->get(route('dashboard.fcm_tokens.index'))
            ->assertOk()
            ->assertSee('android', false);

        $this->delete(route('dashboard.fcm_tokens.destroy', $token->id))
            ->assertRedirect(route('dashboard.fcm_tokens.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('user_fcm_tokens', ['id' => $token->id]);
    }

    public function test_mark_all_read_respects_school_scope(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School B',
            'province' => 'P',
            'district' => '2',
            'address' => 'B',
            'status' => 'active',
        ]);

        $parentA = User::factory()->create(['school_id' => $schoolA->id]);
        $parentB = User::factory()->create(['school_id' => $schoolB->id]);

        InAppNotification::query()->create([
            'user_id' => $parentA->id,
            'title' => 'A',
            'body' => 'A',
        ]);
        $other = InAppNotification::query()->create([
            'user_id' => $parentB->id,
            'title' => 'B',
            'body' => 'B',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $schoolA->id]);
        $this->actingAs($staff);

        $this->post(route('dashboard.in_app_notifications.mark_all_read'))
            ->assertRedirect();

        $this->assertNotNull(InAppNotification::query()->where('user_id', $parentA->id)->value('read_at'));
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_staff_cannot_mark_read_out_of_scope_notification(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School B',
            'province' => 'P',
            'district' => '2',
            'address' => 'B',
            'status' => 'active',
        ]);

        $parentB = User::factory()->create(['school_id' => $schoolB->id]);
        $notification = InAppNotification::query()->create([
            'user_id' => $parentB->id,
            'title' => 'Other',
            'body' => 'School B',
        ]);

        $staff = User::factory()->create(['is_admin' => false, 'school_id' => $schoolA->id]);
        $this->actingAs($staff);

        $this->post(route('dashboard.in_app_notifications.mark_read', $notification->id))
            ->assertNotFound();

        $this->get(route('dashboard.in_app_notifications.show', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
