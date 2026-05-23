<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Services\Notifications\NotificationContractListService;
use App\Services\Notifications\NotificationContractMapper;
use App\Services\Push\FcmTokenRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * StudentWay notifications API contract.
 *
 * @see docs/NOTIFICATIONS_API_CONTRACT.md
 */
class NotificationsContractController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly FcmTokenRegistrar $fcmRegistrar,
        private readonly NotificationContractListService $contractList,
    ) {}

    /**
     * Contract notes enforced:
     * - Plain array in `data` (no pagination object).
     * - No filtering query parameters (422 if sent).
     * - Only {@see NotificationContractListService::ITEM_KEYS} per item (no deep links).
     * - Read state from `read_at` via {@see NotificationContractMapper::toContractItem()}.
     * - Newest → oldest (`orderByDesc id`).
     * - `createdAt` ISO-8601 UTC with `Z` suffix.
     */
    public function index(Request $request): JsonResponse
    {
        $blocked = $this->contractList->blockedQueryParams($request);
        if ($blocked !== []) {
            return $this->parentError(
                'This endpoint does not support filtering or pagination. Use GET /api/in-app-notifications for filters and pages.',
                ['query' => $blocked],
                422,
            );
        }

        $rows = $this->contractList->listForUser((int) $request->user()->id);

        $data = $rows
            ->map(static fn (InAppNotification $n): array => NotificationContractMapper::toContractItem($n))
            ->values()
            ->all();

        return $this->parentSuccess($data, 'Notifications fetched successfully');
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $row = InAppNotification::query()
            ->whereKey($notification)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($row === null) {
            return $this->parentError('Notification not found', null, 404);
        }

        if ($row->read_at === null) {
            $row->update(['read_at' => now()]);
        }

        return $this->parentSuccess(null, 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->parentSuccess(null, 'All notifications marked as read');
    }

    public function registerFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:512'],
            'platform' => ['nullable', 'string', Rule::in(['ios', 'android', 'web'])],
            'device_id' => ['nullable', 'string', 'max:128'],
        ]);

        $this->fcmRegistrar->register(
            $request->user(),
            $validated['token'],
            $validated['platform'] ?? null,
            $validated['device_id'] ?? null,
        );

        return $this->parentSuccess(null, 'FCM token registered successfully');
    }
}
