<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_config_returns_pusher_fields_when_configured(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.options.cluster' => 'eu',
            'realtime.laravel_echo.key' => 'test-key',
            'realtime.laravel_echo.cluster' => 'eu',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/chat/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.laravel_echo.key', 'test-key')
            ->assertJsonPath('data.laravel_echo.cluster', 'eu')
            ->assertJsonPath('data.event_name', 'message.sent');
    }

    public function test_user_can_start_conversation_and_send_message(): void
    {
        Event::fake([ChatMessageSent::class]);

        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $this->postJson('/api/chat/conversations', ['subject' => 'Help'])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Help');

        $conversationId = ChatConversation::query()->value('id');

        $this->postJson("/api/chat/conversations/{$conversationId}/messages", [
            'body' => 'Hello support',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello support');

        Event::assertDispatched(ChatMessageSent::class);

        $this->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_admin_can_reply_and_list_conversations(): void
    {
        Event::fake([ChatMessageSent::class]);

        $parent = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $parent->id,
            'status' => 'open',
            'user_last_read_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'body' => 'How can we help?',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sender.is_staff', true);
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::query()->create([
            'user_id' => $owner->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    public function test_mark_read_clears_unread_count(): void
    {
        $parent = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $parent->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'user_id' => $admin->id,
            'body' => 'Staff reply',
        ]);

        Sanctum::actingAs($parent);

        $this->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.items.0.unread_count', 1);

        $this->postJson("/api/chat/conversations/{$conversation->id}/read")
            ->assertOk();

        $this->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.items.0.unread_count', 0);
    }
}
