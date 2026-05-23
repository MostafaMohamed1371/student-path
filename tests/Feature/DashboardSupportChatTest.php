<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardSupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_support_chat(): void
    {
        $this->get(route('dashboard.support_chat.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_support_chat(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        $this->get(route('dashboard.support_chat.index'))->assertForbidden();
    }

    public function test_admin_can_view_list_and_reply(): void
    {
        Event::fake([ChatMessageSent::class]);

        $parent = User::factory()->create(['is_admin' => false, 'name' => 'Parent One']);
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Support Admin']);

        $conversation = ChatConversation::query()->create([
            'user_id' => $parent->id,
            'status' => 'open',
            'user_last_read_at' => now(),
        ]);

        $conversation->messages()->create([
            'user_id' => $parent->id,
            'body' => 'I need help',
        ]);

        $this->actingAs($admin);

        $this->get(route('dashboard.support_chat.index'))
            ->assertOk()
            ->assertSee(__('dashboard.menu_support_chat'), false)
            ->assertSee('Parent One', false);

        $this->get(route('dashboard.support_chat.show', $conversation))
            ->assertOk()
            ->assertSee('I need help', false);

        $this->postJson(route('dashboard.support_chat.messages.store', $conversation), [
            'body' => 'We are here to help',
        ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'We are here to help')
            ->assertJsonPath('message.sender.is_staff', true);

        Event::assertDispatched(ChatMessageSent::class);

        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'body' => 'We are here to help',
        ]);
    }

    public function test_admin_can_close_conversation(): void
    {
        $parent = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $parent->id,
            'status' => 'open',
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.support_chat.close', $conversation))
            ->assertRedirect(route('dashboard.support_chat.show', $conversation));

        $this->assertSame('closed', $conversation->fresh()->status);
    }
}
