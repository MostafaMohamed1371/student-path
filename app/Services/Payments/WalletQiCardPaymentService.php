<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletQicardPayment;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletQiCardPaymentService
{
    public function __construct(
        private QiCardPaymentClient $client
    ) {}

    public function isAvailable(): bool
    {
        if (! config('qicard.enabled')) {
            return false;
        }

        return config('qicard.api_host') !== ''
            && config('qicard.username') !== ''
            && config('qicard.password') !== ''
            && config('qicard.terminal_id') !== '';
    }

    /**
     * @param  array<string, mixed>  $input  validated: amount, currency?, locale?, description?, customer_info?
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, message: string}
     */
    public function initiate(User $user, array $input): array
    {
        if (! $this->isAvailable()) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'QiCard payments are not configured or disabled.',
            ];
        }

        $currency = (string) ($input['currency'] ?? 'IQD');
        $amount = (string) $input['amount'];
        $locale = (string) ($input['locale'] ?? 'US');
        $description = (string) ($input['description'] ?? 'Wallet top-up');
        /** @var array<string, mixed> $customerInfo */
        $customerInfo = is_array($input['customer_info'] ?? null) ? $input['customer_info'] : [];

        $payment = WalletQicardPayment::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'request_id' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'locale' => $locale,
            'description' => $description,
            'customerInfo' => $customerInfo,
            'finishPaymentUrl' => $this->finishPaymentUrl(),
            'notificationUrl' => $this->notificationUrl(),
            'requestId' => $payment->request_id,
            'additionalInfo' => [
                'user_id' => (string) $user->id,
            ],
        ];

        $created = $this->client->createPayment($payload);
        $payment->forceFill(['gateway_create_response' => $created])->save();

        $paymentId = $created['paymentId'] ?? $created['payment_id'] ?? null;
        $formUrl = $created['formUrl'] ?? $created['form_url'] ?? null;

        if (! is_string($paymentId) || $paymentId === '' || ! is_string($formUrl) || $formUrl === '') {
            $payment->forceFill(['status' => 'failed'])->save();

            return [
                'ok' => false,
                'status' => 502,
                'message' => 'QiCard did not return a payment session. Try again later.',
            ];
        }

        $payment->forceFill([
            'payment_id' => $paymentId,
            'form_url' => $formUrl,
        ])->save();

        return [
            'ok' => true,
            'data' => [
                'payment_id' => $paymentId,
                'request_id' => $payment->request_id,
                'form_url' => $formUrl,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
            ],
        ];
    }

    /**
     * Verify status with QiCard and credit the wallet once when successful.
     *
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, message: string}
     */
    public function finalizeByPaymentId(string $paymentId): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return ['ok' => false, 'status' => 422, 'message' => 'payment_id is required.'];
        }

        if (! $this->isAvailable()) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'QiCard payments are not configured or disabled.',
            ];
        }

        $statusBody = $this->client->getPaymentStatus($paymentId);

        $payment = WalletQicardPayment::query()->where('payment_id', $paymentId)->first();
        if (! $payment) {
            return ['ok' => false, 'status' => 404, 'message' => 'Payment not found.'];
        }

        $payment->forceFill(['gateway_status_response' => $statusBody])->save();

        $remotePaymentId = $statusBody['paymentId'] ?? $statusBody['payment_id'] ?? null;
        if (is_string($remotePaymentId) && $remotePaymentId !== '' && $remotePaymentId !== $payment->payment_id) {
            return ['ok' => false, 'status' => 409, 'message' => 'Payment identifier mismatch.'];
        }

        $status = $statusBody['status'] ?? null;
        $canceled = (bool) ($statusBody['canceled'] ?? false);
        $upper = is_string($status) ? strtoupper($status) : '';

        if ($upper === 'SUCCESS' && ! $canceled) {
            return $this->creditIfNeeded($payment);
        }

        if ($upper === 'SUCCESS' && $canceled) {
            $payment->forceFill(['status' => 'canceled'])->save();

            return [
                'ok' => true,
                'data' => [
                    'credited' => false,
                    'payment_status' => 'canceled',
                ],
            ];
        }

        if (in_array($upper, ['FAILED', 'FAILURE', 'DECLINED', 'REJECTED', 'CANCELED', 'CANCELLED'], true)) {
            $payment->forceFill(['status' => 'failed'])->save();
        }

        return [
            'ok' => true,
            'data' => [
                'credited' => false,
                'payment_status' => is_string($status) ? $status : null,
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}
     */
    private function creditIfNeeded(WalletQicardPayment $payment): array
    {
        $userId = $payment->user_id;
        $amount = (string) $payment->amount;
        $currency = $payment->currency;
        $paymentId = (string) $payment->payment_id;

        $walletPayload = DB::transaction(function () use ($payment, $userId, $amount, $currency, $paymentId): array {
            $locked = WalletQicardPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->credited_at !== null) {
                $wallet = Wallet::query()->firstOrCreate(
                    ['user_id' => $userId],
                    ['balance' => 0, 'currency' => $currency]
                );

                return [
                    'credited' => true,
                    'already_credited' => true,
                    'balance' => (string) $wallet->balance,
                    'currency' => $wallet->currency,
                    'payment_status' => 'paid',
                ];
            }

            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0, 'currency' => $currency]
            );

            $w = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($currency !== '' && $w->currency !== $currency) {
                $w->forceFill(['currency' => $currency])->save();
            }

            $newBalance = bcadd((string) $w->balance, $amount, 2);
            $w->forceFill(['balance' => $newBalance])->save();

            WalletTransaction::query()->create([
                'wallet_id' => $w->id,
                'type' => 'recharge',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'meta' => [
                    'gateway' => 'qicard',
                    'payment_id' => $paymentId,
                    'request_id' => $locked->request_id,
                    'currency' => $currency,
                ],
            ]);

            $locked->forceFill([
                'status' => 'paid',
                'credited_at' => now(),
            ])->save();

            return [
                'credited' => true,
                'already_credited' => false,
                'balance' => (string) $w->balance,
                'currency' => $w->currency,
                'payment_status' => 'paid',
            ];
        });

        return ['ok' => true, 'data' => $walletPayload];
    }

    private function finishPaymentUrl(): string
    {
        $override = config('qicard.finish_payment_url');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return url(config('qicard.finish_payment_path'));
    }

    private function notificationUrl(): string
    {
        $override = config('qicard.notification_url');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return url(config('qicard.notification_path'));
    }
}
