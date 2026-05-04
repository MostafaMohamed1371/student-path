<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QiCardWalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'qicard.enabled' => true,
            'qicard.block_direct_recharge' => false,
            'qicard.api_host' => 'https://qi.test',
            'qicard.username' => 'user',
            'qicard.password' => 'secret',
            'qicard.terminal_id' => 'terminal-1',
        ]);
    }

    public function test_init_returns_503_when_qicard_disabled(): void
    {
        config(['qicard.enabled' => false]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/payments/qicard/init', ['amount' => 10])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_init_returns_502_when_gateway_response_incomplete(): void
    {
        Http::fake([
            'https://qi.test/payment' => Http::response(['error' => 'bad'], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/payments/qicard/init', ['amount' => 10])
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('wallet_qicard_payments', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);
    }

    public function test_webhook_credits_wallet_once_and_finish_is_idempotent(): void
    {
        Http::fake([
            'https://qi.test/payment' => Http::response([
                'paymentId' => 'pay-xyz',
                'formUrl' => 'https://pay.qi/form',
            ], 200),
            'https://qi.test/payment/pay-xyz/status' => Http::response([
                'paymentId' => 'pay-xyz',
                'status' => 'SUCCESS',
                'canceled' => false,
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/payments/qicard/init', ['amount' => 25, 'currency' => 'IQD'])
            ->assertCreated()
            ->assertJsonPath('data.payment_id', 'pay-xyz')
            ->assertJsonPath('data.form_url', 'https://pay.qi/form');

        $this->postJson('/api/webhooks/qicard', ['paymentId' => 'pay-xyz'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credited', true)
            ->assertJsonPath('data.already_credited', false);

        $this->postJson('/api/webhooks/qicard', ['paymentId' => 'pay-xyz'])
            ->assertOk()
            ->assertJsonPath('data.credited', true)
            ->assertJsonPath('data.already_credited', true);

        $this->get('/api/wallet/payments/qicard/finish?paymentId=pay-xyz')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credited', true)
            ->assertJsonPath('data.already_credited', true);

        Sanctum::actingAs($user);
        $this->getJson('/api/wallet')->assertOk()->assertJsonPath('data.balance', '25.00');

        $this->getJson('/api/wallet/transactions')->assertOk()->assertJsonPath('data.pagination.total', 1);
    }

    public function test_direct_recharge_forbidden_when_qicard_blocks_it(): void
    {
        config(['qicard.block_direct_recharge' => true]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/recharge', ['amount' => 5])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
