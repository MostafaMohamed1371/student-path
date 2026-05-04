<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InAppNotificationController extends Controller
{
    use FormatsParentApiResponse;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread_only' => ['nullable', 'boolean'],
        ]);

        $q = InAppNotification::query()->where('user_id', $request->user()->id);

        if ($request->boolean('unread_only')) {
            $q->whereNull('read_at');
        }

        $unreadCount = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        $rows = $q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $items = collect($rows->items())->map(static function (InAppNotification $n): array {
            return [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return $this->parentSuccess([
            'items' => $items,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => [
                'integer',
                Rule::exists('in_app_notifications', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $q = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at');

        if (! empty($validated['ids'])) {
            $q->whereIn('id', $validated['ids']);
        }

        $q->update(['read_at' => now()]);

        return $this->parentSuccess((object) [], 'Notifications marked as read');
    }

    public function destroy(Request $request, InAppNotification $notification): JsonResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $notification->delete();

        return $this->parentSuccess((object) [], 'Notification deleted');
    }
}
