<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Dashboard\InAppNotificationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesDashboardInAppNotificationFilters
{
    /**
     * @return array{
     *     showNotificationTypeFilter: bool,
     *     filterNotificationType: string,
     *     notificationTypeOptions: list<string>,
     *     showUnreadFilter: bool,
     *     filterUnreadOnly: bool
     * }
     */
    protected function dashboardInAppNotificationExtraFilters(Request $request): array
    {
        $filterNotificationType = '';
        $rawType = trim((string) $request->query('notification_type', ''));
        if ($rawType !== '' && in_array($rawType, InAppNotificationPresenter::filterableTypes(), true)) {
            $filterNotificationType = $rawType;
        }

        return [
            'showNotificationTypeFilter' => true,
            'filterNotificationType' => $filterNotificationType,
            'notificationTypeOptions' => InAppNotificationPresenter::filterableTypes(),
            'showUnreadFilter' => true,
            'filterUnreadOnly' => $request->boolean('unread_only'),
        ];
    }

    /**
     * @param  Builder<\App\Models\InAppNotification>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyDashboardInAppNotificationFilters(Builder $query, array $filters): void
    {
        if ($filters['restrictEmpty'] ?? false) {
            return;
        }

        $type = (string) ($filters['filterNotificationType'] ?? '');
        if ($type !== '') {
            $query->where('data->type', $type);
        }

        if ($filters['filterUnreadOnly'] ?? false) {
            $query->whereNull('read_at');
        }
    }
}
