<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Payments\WalletQiCardPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QiCardWalletPaymentController extends Controller
{
    use FormatsParentApiResponse;

    public function init(Request $request, WalletQiCardPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'currency' => ['nullable', 'string', 'max:8'],
            'locale' => ['nullable', 'string', 'max:8'],
            'description' => ['nullable', 'string', 'max:500'],
            'customer_info' => ['nullable', 'array'],
        ]);

        $result = $payments->initiate($request->user(), $validated);
        if (! $result['ok']) {
            /** @var int $status */
            $status = $result['status'];

            return $this->parentError($result['message'], null, $status);
        }

        return $this->parentSuccess($result['data'], 'QiCard payment created', 201);
    }

    public function webhook(Request $request, WalletQiCardPaymentService $payments): JsonResponse
    {
        $paymentId = $request->input('paymentId')
            ?? $request->input('payment_id')
            ?? $request->input('paymentID');

        if (! is_string($paymentId) || trim($paymentId) === '') {
            return response()->json(['success' => false, 'message' => 'payment_id missing'], 422);
        }

        $result = $payments->finalizeByPaymentId($paymentId);
        if (! $result['ok']) {
            /** @var int $status */
            $status = $result['status'];

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    public function finish(Request $request, WalletQiCardPaymentService $payments): JsonResponse
    {
        $paymentId = $request->query('paymentId')
            ?? $request->query('payment_id')
            ?? $request->input('paymentId')
            ?? $request->input('payment_id');

        if (! is_string($paymentId)) {
            return $this->parentError('payment_id is required.', null, 422);
        }

        $result = $payments->finalizeByPaymentId($paymentId);
        if (! $result['ok']) {
            /** @var int $status */
            $status = $result['status'];

            return $this->parentError($result['message'], null, $status);
        }

        return $this->parentSuccess($result['data'], 'Payment status processed');
    }
}
