<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Api\Legacy\Concerns\RespondsWithLegacySuccess;
use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy contract: GET /api/notifications, GET /api/haveNewMessages.
 */
class LegacyNotificationsController extends Controller
{
    use RespondsWithLegacySuccess;

    public function index(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $rows = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit($limit)
            ->get();

        Carbon::setLocale(app()->getLocale());

        $data = $rows->map(function (InAppNotification $n): array {
            $created = $n->created_at ?? now();

            return [
                'id' => (string) $n->id,
                'type' => $this->mapNotificationType($n),
                'title' => (string) $n->title,
                'body' => (string) $n->body,
                'time_ago' => $created->diffForHumans(),
                'is_read' => $n->read_at !== null,
            ];
        })->values()->all();

        return $this->legacySuccess($data);
    }

    public function haveNewMessages(Request $request): JsonResponse
    {
        $unreadCount = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->legacySuccess([
            'hasNewMessages' => $unreadCount > 0,
            'unreadCount' => $unreadCount,
        ]);
    }

    private function mapNotificationType(InAppNotification $n): string
    {
        $data = $n->data ?? [];
        if (! empty($data['type']) && is_string($data['type'])) {
            $t = strtoupper($data['type']);
            $tripTypes = [
                'TRIP_STARTED', 'TRIP_COMPLETED', 'RETURN_TRIP_STARTED', 'RETURN_TRIP_COMPLETED',
                'TRIP_STUDENT_ARRIVED', 'DELAY_ALERT', 'SOS_TRIGGERED',
            ];
            if (in_array($t, $tripTypes, true)) {
                return 'TRIP_STATUS';
            }

            $allowed = ['TRIP', 'TRIP_STATUS', 'WALLET', 'WALLET_TRANSACTION', 'USER_INFO', 'CHAT', 'SUPPORT', 'SYSTEM'];
            if (in_array($t, $allowed, true)) {
                if ($t === 'TRIP_STATUS') {
                    return 'TRIP_STATUS';
                }
                if ($t === 'WALLET_TRANSACTION') {
                    return 'WALLET_TRANSACTION';
                }

                return $t;
            }
        }

        return 'USER_INFO';
    }
}
