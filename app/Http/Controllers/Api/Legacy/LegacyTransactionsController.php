<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Api\Legacy\Concerns\RespondsWithLegacySuccess;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy contract: GET /api/transactions (flat list; parent wallet detail is GET /api/wallet/transactions).
 */
class LegacyTransactionsController extends Controller
{
    use RespondsWithLegacySuccess;

    public function index(Request $request): JsonResponse
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'currency' => 'IQD']
        );

        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $rows = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->limit($limit)
            ->get();

        $data = $rows->map(fn (WalletTransaction $tx) => $this->formatTransaction($tx))->values()->all();

        return $this->legacySuccess($data);
    }

    /**
     * @return array{id: string, amount: float, date: string, title: string, status: string, type: string}
     */
    private function formatTransaction(WalletTransaction $tx): array
    {
        $meta = $tx->meta ?? [];
        $type = $this->mapTxType((string) $tx->type);
        $status = $this->mapTxStatus($meta);

        return [
            'id' => '#TXN-'.$tx->id,
            'amount' => (float) $tx->amount,
            'date' => $tx->created_at?->toIso8601String() ?? '',
            'title' => $this->transactionTitle($tx, $type),
            'status' => $status,
            'type' => $type,
        ];
    }

    private function mapTxType(string $dbType): string
    {
        return match (strtolower($dbType)) {
            'recharge' => 'CHARGE',
            'withdraw', 'withdrawal' => 'WITHDREW',
            default => 'PAYMENT',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function mapTxStatus(array $meta): string
    {
        $raw = isset($meta['status']) ? strtoupper((string) $meta['status']) : '';
        if (in_array($raw, ['COMPLETED', 'PENDING', 'FAILED'], true)) {
            return $raw;
        }

        return 'COMPLETED';
    }

    private function transactionTitle(WalletTransaction $tx, string $legacyType): string
    {
        $meta = $tx->meta ?? [];
        if (! empty($meta['title']) && is_string($meta['title'])) {
            return $meta['title'];
        }
        if (! empty($meta['description']) && is_string($meta['description'])) {
            return $meta['description'];
        }

        $ar = app()->getLocale() === 'ar';

        return match ($legacyType) {
            'CHARGE' => $ar ? 'شحن المحفظة' : 'Wallet charge',
            'WITHDREW' => $ar ? 'سحب من المحفظة' : 'Wallet withdrawal',
            default => $ar ? 'دفع / معاملة' : 'Payment / transaction',
        };
    }
}
