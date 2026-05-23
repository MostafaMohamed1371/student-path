<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Push\FcmTokenRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FcmTokenController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly FcmTokenRegistrar $registrar,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:512'],
            'platform' => ['nullable', 'string', Rule::in(['ios', 'android', 'web'])],
            'device_id' => ['nullable', 'string', 'max:128'],
        ]);

        $row = $this->registrar->register(
            $request->user(),
            $validated['token'],
            $validated['platform'] ?? null,
            $validated['device_id'] ?? null,
        );

        return $this->parentSuccess([
            'id' => $row->id,
            'platform' => $row->platform,
            'device_id' => $row->device_id,
            'last_used_at' => $row->last_used_at?->toIso8601String(),
        ], 'FCM token registered');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:512'],
        ]);

        $removed = $this->registrar->unregister($request->user(), $validated['token']);

        if (! $removed) {
            return $this->parentError('FCM token not found', null, 404);
        }

        return $this->parentSuccess((object) [], 'FCM token removed');
    }
}
