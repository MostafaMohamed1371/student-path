<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatSendMessageResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_message_succeeds_when_pusher_broadcast_fails(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'invalid-key',
            'broadcasting.connections.pusher.secret' => 'invalid-secret',
            'broadcasting.connections.pusher.app_id' => '0',
            'broadcasting.connections.pusher.options.cluster' => 'mt1',
            'fcm.enabled' => false,
        ]);

        $user = User::factory()->create(['is_admin' => false]);
        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/user/chats/{$conversation->id}/messages", [
            'message_type' => 'text',
            'body' => 'Hello',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conversation->id,
            'body' => 'Hello',
        ]);
    }
}
