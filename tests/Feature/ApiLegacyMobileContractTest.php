<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\SupportComplaint;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiLegacyMobileContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_info_and_categories_are_public_and_match_shape(): void
    {
        $this->getJson('/api/support/info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'success')
            ->assertJsonStructure([
                'data' => [
                    'contactMethods' => ['whatsapp', 'phone', 'liveChat'],
                    'faqs',
                ],
            ]);

        $this->getJson('/api/support/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', '1')
            ->assertJsonPath('data.0.label', 'مشكلة في الحساب');
    }

    public function test_legacy_transactions_shape(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 100,
            'currency' => 'IQD',
        ]);
        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'recharge',
            'amount' => 25000,
            'balance_after' => 25000,
            'meta' => ['title' => 'شحن المحفظة - زين كاش', 'status' => 'COMPLETED'],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'success')
            ->assertJsonPath('data.0.type', 'CHARGE')
            ->assertJsonPath('data.0.status', 'COMPLETED')
            ->assertJsonPath('data.0.title', 'شحن المحفظة - زين كاش')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'amount', 'date', 'title', 'status', 'type'],
                ],
            ]);
    }

    public function test_legacy_notifications_and_have_new_messages(): void
    {
        $user = User::factory()->create();
        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'رحلة جديدة',
            'body' => 'بدأت الرحلة',
            'data' => ['type' => 'TRIP_STATUS'],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?legacy=1')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'TRIP_STATUS')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'body', 'time_ago', 'is_read'],
                ],
            ]);

        $this->getJson('/api/haveNewMessages')
            ->assertOk()
            ->assertJsonPath('data.hasNewMessages', true)
            ->assertJsonPath('data.unreadCount', 1);
    }

    public function test_legacy_user_settings_and_performance(): void
    {
        $user = User::factory()->create(['rate' => 4.9, 'votes' => 150]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user/settings/notifications')
            ->assertOk()
            ->assertJsonPath('data.tripNotifications.busMovement', true);

        $this->getJson('/api/user/performance')
            ->assertOk()
            ->assertJsonPath('data.overallRating.rating', 4.9)
            ->assertJsonPath('data.overallRating.totalReviews', 150)
            ->assertJsonStructure([
                'data' => [
                    'overallRating',
                    'tripStats',
                    'commitmentStatus',
                    'ratingBreakdown',
                    'parentComments',
                ],
            ]);
    }

    public function test_support_complaint_submission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/support/complaint', [
            'category_id' => '1',
            'details' => 'Test complaint body',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.attachmentCount', 0);

        $this->assertDatabaseCount('support_complaints', 1);
        $num = SupportComplaint::query()->value('complaint_number');
        $this->assertStringStartsWith('#CMP-', (string) $num);
    }

    public function test_support_complaint_accepts_multiple_attachments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/support/complaint', [
            'category_id' => '2',
            'details' => 'Complaint with screenshots',
            'attachments' => [
                \Illuminate\Http\UploadedFile::fake()->image('screen1.jpg'),
                \Illuminate\Http\UploadedFile::fake()->image('screen2.png'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.attachmentCount', 2);

        $stored = SupportComplaint::query()->firstOrFail();
        $this->assertIsArray($stored->attachments);
        $this->assertCount(2, $stored->attachments);
    }
}
