<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * GET /api/notifications (StudentWay contract) list rules.
 *
 * @see docs/NOTIFICATIONS_API_CONTRACT.md#notes
 */
final class NotificationContractListService
{
    /** @var list<string> Allowed public fields per notification item (no deep links / actions). */
    public const ITEM_KEYS = ['id', 'title', 'body', 'type', 'isRead', 'createdAt'];

    /**
     * Query parameters that must not be used on the contract list (filtering / pagination).
     *
     * @var list<string>
     */
    private const BLOCKED_QUERY_PARAMS = [
        'unread_only',
        'type',
        'notification_type',
        'page',
        'per_page',
        'limit',
        'cursor',
        'filter',
        'search',
        'q',
    ];

    /**
     * @return list<string> Parameter names present on the request that are not allowed.
     */
    public function blockedQueryParams(Request $request): array
    {
        $present = array_keys($request->query());

        return array_values(array_intersect(self::BLOCKED_QUERY_PARAMS, $present));
    }

    /**
     * Newest first, no pagination metadata. Optional safety cap via config (0 = no cap).
     *
     * @return Collection<int, InAppNotification>
     */
    public function listForUser(int $userId): Collection
    {
        $query = InAppNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('id');

        $max = (int) config('notifications.contract_max_list', 500);
        if ($max > 0) {
            $query->limit($max);
        }

        return $query->get();
    }
}
