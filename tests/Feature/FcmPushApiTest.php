<?php

namespace Tests\Feature;

use App\Contracts\Push\PushNotifier;
use App\Models\InAppNotification;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class FcmPushApiTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_TOKEN = 'dGhpcyBpcyBhIGZha2UgZmNtIHRva2VuIGZvciB0ZXN0aW5nIG9ubHkgMTIzNDU2';

    public function test_register_and_remove_fcm_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/user/fcm-token', [
            'token' => self::SAMPLE_TOKEN,
            'platform' => 'android',
            'device_id' => 'pixel-7',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => self::SAMPLE_TOKEN,
            'platform' => 'android',
            'device_id' => 'pixel-7',
        ]);

        $this->deleteJson('/api/user/fcm-token', ['token' => self::SAMPLE_TOKEN])
            ->assertOk();

        $this->assertDatabaseMissing('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => self::SAMPLE_TOKEN,
        ]);
    }

    public function test_registering_token_reassigns_same_device_to_current_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        UserFcmToken::query()->create([
            'user_id' => $first->id,
            'token' => self::SAMPLE_TOKEN,
            'platform' => 'ios',
        ]);

        Sanctum::actingAs($second);

        $this->postJson('/api/user/fcm-token', [
            'token' => self::SAMPLE_TOKEN,
            'platform' => 'ios',
        ])->assertOk();

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $second->id,
            'token' => self::SAMPLE_TOKEN,
        ]);
        $this->assertDatabaseMissing('user_fcm_tokens', [
            'user_id' => $first->id,
            'token' => self::SAMPLE_TOKEN,
        ]);
    }

    public function test_in_app_notification_triggers_push_when_fcm_enabled(): void
    {
        Config::set('fcm.enabled', true);

        $push = Mockery::mock(PushNotifier::class);
        $user = User::factory()->create();

        $push->shouldReceive('notifyUser')
            ->once()
            ->withArgs(function ($userId, string $title, ?string $body, array $data) use ($user): bool {
                return (int) $userId === (int) $user->id
                    && $title === 'Test title'
                    && $body === 'Test body'
                    && ($data['type'] ?? null) === 'TEST';
            });
        $this->app->instance(PushNotifier::class, $push);

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Test title',
            'body' => 'Test body',
            'data' => ['type' => 'TEST', 'trip_id' => 'TRIP-1'],
        ]);
    }

    public function test_in_app_notification_does_not_push_when_fcm_disabled(): void
    {
        Config::set('fcm.enabled', false);

        $push = Mockery::mock(PushNotifier::class);
        $push->shouldNotReceive('notifyUser');
        $this->app->instance(PushNotifier::class, $push);

        $user = User::factory()->create();

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Silent',
            'body' => 'No push',
        ]);
    }
}
