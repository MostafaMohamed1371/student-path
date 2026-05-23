<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\ChatTypingStatusUpdated;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_postman_list_start_messages_read_flow(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Support Agent']);
        $user = User::factory()->create(['is_admin' => false, 'name' => 'Parent User']);
        Sanctum::actingAs($user);

        $start = $this->postJson('/api/user/chats/start', [
            'participant_id' => $admin->id,
            'post_id' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('existing', false)
            ->assertJsonStructure(['data' => ['id', 'post_id', 'other_user', 'unread_count']]);

        $chatId = (int) $start->json('data.id');

        $this->getJson('/api/user/chats?search=Parent&per_page=20')
            ->assertOk()
            ->assertJsonPath('message', 'Conversations retrieved successfully.')
            ->assertJsonStructure(['data', 'pagination']);

        $this->postJson("/api/user/chats/{$chatId}/messages", [
            'message_type' => 'text',
            'body' => 'مرحبا، هل الإعلان متاح؟',
        ])->assertCreated()
            ->assertJsonPath('data.message_type', 'text');

        $this->getJson("/api/user/chats/{$chatId}/messages?per_page=30")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($admin);

        $this->postJson("/api/user/chats/{$chatId}/read")
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);
    }

    public function test_postman_offer_accept_reject_counter_and_thread(): void
    {
        Event::fake([ChatMessageSent::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'participant_id' => $admin->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $offerResponse = $this->postJson("/api/user/chats/{$conversation->id}/messages", [
            'message_type' => 'offer',
            'body' => 'عرض سعر مبدئي',
            'meta' => [
                'amount' => 125000,
                'currency' => 'IQD',
                'title' => 'عرض شراء',
                'details' => 'الدفع خلال 3 أيام',
                'valid_until' => now()->addDays(3)->toIso8601String(),
            ],
        ])->assertCreated();

        $messageId = (int) $offerResponse->json('data.id');

        Sanctum::actingAs($admin);

        $this->postJson("/api/user/chats/{$conversation->id}/messages/{$messageId}/offer/reject", [
            'reason' => 'السعر غير مناسب',
        ])
            ->assertOk()
            ->assertJsonPath('data.offer.status', 'rejected');

        $conversation->update(['status' => 'open']);
        $offer = ChatMessage::query()->findOrFail($messageId);
        $offer->update(['meta' => array_merge($offer->meta ?? [], ['status' => 'pending'])]);

        $this->postJson("/api/user/chats/{$conversation->id}/messages/{$messageId}/offer/counter", [
            'amount' => 98000,
            'currency' => 'IQD',
            'title' => 'عرض مقابل',
            'body' => 'Counter offer submitted',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['updated_original_offer', 'counter_offer_message'],
            ]);

        $this->getJson("/api/user/chats/{$conversation->id}/offers/{$messageId}/thread")
            ->assertOk()
            ->assertJsonPath('message', 'Offer thread retrieved successfully.');
    }

    public function test_postman_edit_delete_and_typing(): void
    {
        Event::fake([ChatMessageSent::class, ChatTypingStatusUpdated::class]);

        $user = User::factory()->create(['is_admin' => false]);
        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $messageId = (int) $this->postJson("/api/user/chats/{$conversation->id}/messages", [
            'message_type' => 'text',
            'body' => 'original',
        ])->json('data.id');

        $this->putJson("/api/user/chats/{$conversation->id}/messages/{$messageId}", [
            'body' => 'تم تعديل الرسالة',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_edited', true);

        $this->postJson("/api/user/chats/{$conversation->id}/typing", ['is_typing' => true])
            ->assertOk();

        Event::assertDispatched(ChatTypingStatusUpdated::class);

        $this->deleteJson("/api/user/chats/{$conversation->id}/messages/{$messageId}")
            ->assertOk()
            ->assertJsonPath('data.id', $messageId);

        $this->assertDatabaseMissing('chat_messages', ['id' => $messageId]);
    }

    public function test_pin_read_unread_block_and_unread_count(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'participant_id' => $admin->id,
            'status' => 'open',
        ]);

        $conversation->messages()->create([
            'user_id' => $admin->id,
            'body' => 'Hello',
            'message_type' => 'text',
            'read_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/user/chats/{$conversation->id}/preferences", ['is_pinned' => true])
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->postJson("/api/user/chats/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->getJson('/api/user/chats/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->postJson("/api/user/chats/{$conversation->id}/unread")
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->getJson('/api/user/chats/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson("/api/user/chats/{$conversation->id}/block-user", ['reason' => 'spam'])
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true);

        $this->postJson("/api/user/chats/{$conversation->id}/messages", [
            'message_type' => 'text',
            'body' => 'blocked send attempt',
        ])->assertStatus(403);

        $this->postJson("/api/user/chats/{$conversation->id}/unblock-user")
            ->assertOk()
            ->assertJsonPath('data.is_blocked', false);

        $this->postJson("/api/user/chats/{$conversation->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->postJson("/api/user/chats/{$conversation->id}/unpin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', false);
    }

    public function test_postman_file_message_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['is_admin' => false]);
        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $this->post("/api/user/chats/{$conversation->id}/messages", [
            'message_type' => 'file',
            'body' => 'مرفق جديد',
            'attachment' => UploadedFile::fake()->image('photo.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.message_type', 'image');
    }
}
