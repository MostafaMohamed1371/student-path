<?php

namespace Tests\Feature;

use App\Enums\PhoneAccountType;
use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\School;
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

        $school = $this->createSchool();
        $this->createSchoolStaff($school);

        $user = User::factory()->create([
            'is_admin' => false,
            'school_id' => $school->id,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/chat/conversations', ['subject' => 'Help'])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Help');

        $conversation = ChatConversation::query()->first();
        $this->assertSame($school->id, $conversation->school_id);

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Hello support',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello support');

        Event::assertDispatched(ChatMessageSent::class);

        $this->getJson("/api/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_school_staff_can_reply_and_list_their_school_conversations(): void
    {
        Event::fake([ChatMessageSent::class]);

        $school = $this->createSchool();
        $schoolStaff = $this->createSchoolStaff($school);
        $parent = User::factory()->create([
            'is_admin' => false,
            'school_id' => $school->id,
        ]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $parent->id,
            'school_id' => $school->id,
            'participant_id' => $schoolStaff->id,
            'status' => 'open',
            'user_last_read_at' => now(),
        ]);

        Sanctum::actingAs($schoolStaff);

        $this->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'body' => 'How can we help?',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sender.is_staff', true);
    }

    public function test_parent_chats_with_school_staff_not_global_admin(): void
    {
        $school = $this->createSchool();
        $schoolStaff = $this->createSchoolStaff($school);
        User::factory()->create(['is_admin' => true]);

        $parent = User::factory()->create([
            'is_admin' => false,
            'school_id' => $school->id,
        ]);

        Sanctum::actingAs($parent);

        $this->postJson('/api/user/chats/start')
            ->assertCreated();

        $conversation = ChatConversation::query()->first();
        $this->assertSame($school->id, $conversation->school_id);
        $this->assertSame($schoolStaff->id, $conversation->participant_id);

        $this->getJson('/api/user/chats')
            ->assertOk()
            ->assertJsonPath('data.0.other_user.id', $schoolStaff->id);
    }

    public function test_school_staff_only_sees_own_school_conversations(): void
    {
        $schoolA = $this->createSchool('School A');
        $schoolB = $this->createSchool('School B');
        $staffA = $this->createSchoolStaff($schoolA);
        $this->createSchoolStaff($schoolB);

        $parentA = User::factory()->create(['school_id' => $schoolA->id, 'is_admin' => false]);
        $parentB = User::factory()->create(['school_id' => $schoolB->id, 'is_admin' => false]);

        ChatConversation::query()->create([
            'user_id' => $parentA->id,
            'school_id' => $schoolA->id,
            'status' => 'open',
        ]);
        ChatConversation::query()->create([
            'user_id' => $parentB->id,
            'school_id' => $schoolB->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($staffA);

        $this->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.user.id', $parentA->id);
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
        $school = $this->createSchool();
        $schoolStaff = $this->createSchoolStaff($school);

        $parent = User::factory()->create(['is_admin' => false, 'school_id' => $school->id]);

        $conversation = ChatConversation::query()->create([
            'user_id' => $parent->id,
            'school_id' => $school->id,
            'participant_id' => $schoolStaff->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'user_id' => $schoolStaff->id,
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

    private function createSchool(string $nameEn = 'Test School'): School
    {
        return School::query()->create([
            'name_en' => $nameEn,
            'name_ar' => $nameEn,
            'province' => 'Baghdad',
            'district' => 'Karkh',
            'address' => 'Test address',
            'status' => 'active',
        ]);
    }

    private function createSchoolStaff(School $school): User
    {
        static $phoneSeq = 7702000000;

        return User::query()->create([
            'name' => 'School Staff',
            'phone' => (string) (++$phoneSeq),
            'school_id' => $school->id,
            'phone_account_type' => PhoneAccountType::School->value,
            'password' => bcrypt('password'),
            'is_admin' => false,
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);
    }
}
