<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_list_mark_read_and_fcm_token(): void
    {
        $user = User::factory()->create();
        $read = InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'تم بدء الرحلة',
            'body' => 'بدأت الرحلة الصباحية',
            'data' => ['type' => 'TRIP_STARTED', 'trip_id' => 'TRP-1'],
            'read_at' => now(),
        ]);
        $unread = InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'تأخير',
            'body' => 'تأخر السائق',
            'data' => ['type' => 'DELAY_ALERT'],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notifications fetched successfully')
            ->assertJsonPath('data.0.id', (string) $unread->id)
            ->assertJsonPath('data.0.type', 'ALERT')
            ->assertJsonPath('data.0.isRead', false)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'body', 'type', 'isRead', 'createdAt'],
                ],
            ]);

        $this->patchJson('/api/notifications/'.$unread->id.'/read')
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read')
            ->assertJsonPath('data', null);

        $this->assertNotNull($unread->fresh()->read_at);

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Another',
            'body' => 'Unread',
            'data' => ['type' => 'CHAT_MESSAGE'],
        ]);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read');

        $this->assertSame(
            0,
            InAppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
        );

        $token = str_repeat('a', 140);
        $this->postJson('/api/notifications/fcm-token', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('message', 'FCM token registered successfully')
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => $token,
        ]);

        $this->assertNotNull($read->fresh());
    }

    public function test_legacy_notifications_query_param(): void
    {
        $user = User::factory()->create();
        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'رحلة',
            'body' => 'بدأت',
            'data' => ['type' => 'TRIP_STARTED'],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?legacy=1')
            ->assertOk()
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'body', 'time_ago', 'is_read'],
                ],
            ]);
    }

    public function test_fcm_observer_includes_contract_data_fields(): void
    {
        config(['fcm.enabled' => true]);

        $logged = [];
        App::bind(\App\Contracts\Push\PushNotifier::class, function () use (&$logged) {
            return new class($logged) implements \App\Contracts\Push\PushNotifier
            {
                public function __construct(private array &$logged) {}

                public function notifyUser(\App\Models\User|int $user, string $title, ?string $body = null, array $data = []): void
                {
                    $this->logged[] = $data;
                }
            };
        });

        $user = User::factory()->create();
        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'token' => str_repeat('b', 140),
            'platform' => 'android',
        ]);

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Trip',
            'body' => 'Started',
            'data' => ['type' => 'TRIP_STARTED', 'trip_id' => 'TRP-9'],
        ]);

        $this->assertNotEmpty($logged);
        $payload = $logged[0];
        $this->assertArrayHasKey('notificationId', $payload);
        $this->assertSame('TRIP', $payload['type']);
        $this->assertSame('Trip', $payload['title']);
        $this->assertSame('Started', $payload['body']);
    }

    public function test_contract_notes_no_pagination_filtering_order_read_state_and_created_at(): void
    {
        $user = User::factory()->create();

        $older = InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Older',
            'body' => 'First created',
            'data' => [
                'type' => 'TRIP_STARTED',
                'trip_id' => 'TRP-1',
                'conversation_id' => 99,
                'deep_link' => 'studentway://trip/1',
            ],
            'read_at' => null,
            'created_at' => now()->subDay(),
        ]);
        $newer = InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Newer',
            'body' => 'Second',
            'data' => ['type' => 'DELAY_ALERT'],
            'read_at' => now(),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $newer->id)
            ->assertJsonPath('data.1.id', (string) $older->id)
            ->assertJsonPath('data.0.isRead', true)
            ->assertJsonPath('data.1.isRead', false);

        $json = $response->json();
        $this->assertIsArray($json['data']);
        $this->assertArrayNotHasKey('pagination', $json);
        $this->assertArrayNotHasKey('items', $json);
        $this->assertArrayNotHasKey('current_page', $json);

        $first = $json['data'][0];
        $this->assertSame(
            \App\Services\Notifications\NotificationContractListService::ITEM_KEYS,
            array_keys($first),
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $first['createdAt'],
        );
        $this->assertStringEndsWith('Z', $first['createdAt']);

        $this->getJson('/api/notifications?unread_only=1')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->getJson('/api/notifications?page=2&per_page=10')
            ->assertStatus(422);

        $this->patchJson('/api/notifications/'.$older->id.'/read')->assertOk();

        $this->getJson('/api/notifications')
            ->assertJsonPath('data.1.isRead', true);

        $this->assertNotNull($older->fresh()->read_at);
    }

    public function test_mark_read_forbidden_for_other_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $n = InAppNotification::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private',
            'body' => 'Body',
        ]);

        Sanctum::actingAs($other);

        $this->patchJson('/api/notifications/'.$n->id.'/read')
            ->assertNotFound();
    }
}
