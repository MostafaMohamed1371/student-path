<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;

trait FormatsParentApiResponse
{
    /**
     * PDF / mobile contract: both {@see $message} and legacy {@code msg}.
     *
     * @param  array<string, mixed>  $merge
     */
    protected function parentSuccess(mixed $data, string $message = 'success', int $status = 200, array $merge = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
        ], $merge), $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    protected function parentError(string $message, ?array $errors = null, int $status = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'msg' => $message,
            'data' => null,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
