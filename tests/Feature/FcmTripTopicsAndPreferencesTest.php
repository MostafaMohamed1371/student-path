<?php

namespace Tests\Feature;

use App\Contracts\Push\FcmTopicSubscriber;
use App\Contracts\Push\PushNotifier;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use App\Models\UserFcmTopicSubscription;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class FcmTripTopicsAndPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_TOKEN = 'dGhpcyBpcyBhIGZha2UgZmNtIHRva2VuIGZvciB0ZXN0aW5nIG9ubHkgMTIzNDU2';

    public function test_notification_settings_get_and_put(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user/settings/notifications')
            ->assertOk()
            ->assertJsonPath('data.tripNotifications.driverDelay', true)
            ->assertJsonPath('data.chatNotifications.messages', true);

        $this->putJson('/api/user/settings/notifications', [
            'chatNotifications' => ['messages' => false],
            'tripNotifications' => ['driverDelay' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.chatNotifications.messages', false)
            ->assertJsonPath('data.tripNotifications.driverDelay', false)
            ->assertJsonPath('data.tripNotifications.sos', true);

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $user->id,
        ]);
    }

    public function test_push_skipped_when_preference_disabled(): void
    {
        Config::set('fcm.enabled', true);

        $user = User::factory()->create();
        UserNotificationPreference::query()->create([
            'user_id' => $user->id,
            'preferences' => [
                'chatNotifications' => ['messages' => false],
            ],
        ]);

        $push = Mockery::mock(PushNotifier::class);
        $push->shouldNotReceive('notifyUser');
        $this->app->instance(PushNotifier::class, $push);

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Chat',
            'body' => 'Hi',
            'data' => ['type' => 'CHAT_MESSAGE'],
        ]);
    }

    public function test_guardian_can_subscribe_to_trip_topic(): void
    {
        Config::set('fcm.enabled', true);
        Config::set('fcm.mock', true);

        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent',
            'phone' => '7700000091',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7700000092',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => 'B-1',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => 'PRESENT',
            'students_preview' => [],
        ]);
        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 1,
        ]);

        $topics = Mockery::mock(FcmTopicSubscriber::class);
        $topics->shouldReceive('subscribe')->once();
        $this->app->instance(FcmTopicSubscriber::class, $topics);

        Sanctum::actingAs($user);

        $this->postJson('/api/trip-tracking/topics/subscribe', [
            'trip_id' => 'TRP-'.$trip->id,
            'token' => self::SAMPLE_TOKEN,
        ])
            ->assertOk()
            ->assertJsonPath('data.topic', 'trip_'.$trip->id);

        $this->assertDatabaseHas('user_fcm_topic_subscriptions', [
            'user_id' => $user->id,
            'topic' => 'trip_'.$trip->id,
            'trip_history_id' => $trip->id,
        ]);
    }

    public function test_unauthorized_user_cannot_subscribe_to_trip_topic(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S1',
            'name_en' => 'S1',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $otherSchool = School::query()->create([
            'name_ar' => 'S2',
            'name_en' => 'S2',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['school_id' => $otherSchool->id]);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => 'B-2',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => 'PRESENT',
            'students_preview' => [],
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/trip-tracking/topics/subscribe', [
            'trip_id' => (string) $trip->id,
        ])->assertStatus(403);
    }

    public function test_list_trip_topic_subscriptions(): void
    {
        $user = User::factory()->create();
        UserFcmTopicSubscription::query()->create([
            'user_id' => $user->id,
            'topic' => 'trip_99',
            'trip_history_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/trip-tracking/topics')
            ->assertOk()
            ->assertJsonPath('data.items.0.topic', 'trip_99');
    }
}
