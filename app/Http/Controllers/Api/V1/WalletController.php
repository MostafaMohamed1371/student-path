<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    use FormatsParentApiResponse;

    public function show(Request $request): JsonResponse
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'currency' => 'IQD']
        );

        return $this->parentSuccess([
            'balance' => (string) $wallet->balance,
            'currency' => $wallet->currency,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'currency' => 'IQD']
        );

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(static function (WalletTransaction $tx): array {
            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (string) $tx->amount,
                'balance_after' => (string) $tx->balance_after,
                'meta' => $tx->meta,
                'created_at' => $tx->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return $this->parentSuccess([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function recharge(Request $request): JsonResponse
    {
        if (config('qicard.enabled') && config('qicard.block_direct_recharge')) {
            return $this->parentError(
                'Direct wallet recharge is disabled. Use QiCard to add balance.',
                null,
                403
            );
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'payment_method' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $idempotencyRaw = $request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? null);

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'currency' => 'IQD']
        );

        if (is_string($idempotencyRaw) && $idempotencyRaw !== '') {
            $cacheKey = 'wallet_recharge:'.sha1($request->user()->id.'|'.$idempotencyRaw);
            if (Cache::has($cacheKey)) {
                /** @var array{balance: string, currency: string} $cached */
                $cached = Cache::get($cacheKey);

                return $this->parentSuccess($cached, 'Wallet recharged (idempotent)', 200);
            }
        }

        $walletId = $wallet->id;

        DB::transaction(function () use ($walletId, $validated, $idempotencyRaw): void {
            $w = Wallet::query()->whereKey($walletId)->lockForUpdate()->firstOrFail();
            if (! empty($validated['currency'])) {
                $w->forceFill(['currency' => $validated['currency']])->save();
            }
            $amount = (string) $validated['amount'];
            $newBalance = bcadd((string) $w->balance, $amount, 2);
            $w->forceFill(['balance' => $newBalance])->save();

            WalletTransaction::query()->create([
                'wallet_id' => $w->id,
                'type' => 'recharge',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'meta' => array_filter([
                    'reference' => $validated['reference'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'currency' => $validated['currency'] ?? null,
                    'idempotency_key' => is_string($idempotencyRaw) && $idempotencyRaw !== '' ? $idempotencyRaw : null,
                ]),
            ]);
        });

        $wallet->refresh();

        $payload = [
            'balance' => (string) $wallet->balance,
            'currency' => $wallet->currency,
        ];

        if (is_string($idempotencyRaw) && $idempotencyRaw !== '') {
            $cacheKey = 'wallet_recharge:'.sha1($request->user()->id.'|'.$idempotencyRaw);
            Cache::put($cacheKey, $payload, now()->addDay());
        }

        return $this->parentSuccess($payload, 'Wallet recharged', 201);
    }
}
