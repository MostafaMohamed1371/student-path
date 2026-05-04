<?php

namespace App\Http\Controllers\Api\Legacy\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithLegacySuccess
{
    /**
     * @param  array<int|string, mixed>|object  $data
     */
    protected function legacySuccess(array|object $data, string $msg = 'success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'msg' => $msg,
        ], $status);
    }
}
