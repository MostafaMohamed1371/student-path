<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\InAppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait QueriesDashboardInAppNotifications
{
    use AppliesDashboardInAppNotificationFilters;
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

    /**
     * @return array<string, mixed>
     */
    protected function dashboardInAppNotificationFiltersFromRequest(Request $request): array
    {
        return array_merge(
            $this->dashboardReportFilterContext(
                $request,
                withUserRoleFilter: true,
                withGuardianFilter: true,
            ),
            $this->dashboardInAppNotificationExtraFilters($request),
        );
    }

    /**
     * @param  Builder<InAppNotification>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyDashboardInAppNotificationListScopes(Builder $query, array $filters): void
    {
        $this->applyDashboardReportFilters($query, $filters, 'user_relation');

        $role = (string) ($filters['filterUserRole'] ?? '');
        if ($role !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($filters): void {
                $this->applyDashboardUserRoleFilter($userQuery, $filters);
            });
        }

        $guardianId = (int) ($filters['filterGuardianId'] ?? 0);
        if ($guardianId > 0) {
            $query->whereHas('user', function (Builder $userQuery) use ($guardianId): void {
                $this->constrainUsersToGuardian($userQuery, $guardianId);
            });
        }

        $this->applyDashboardInAppNotificationFilters($query, $filters);
    }

    /**
     * @return Builder<InAppNotification>
     */
    protected function dashboardInAppNotificationQuery(Request $request): Builder
    {
        $filters = $this->dashboardInAppNotificationFiltersFromRequest($request);
        $query = InAppNotification::query();
        $this->applyDashboardInAppNotificationListScopes($query, $filters);

        return $query;
    }

    protected function findDashboardInAppNotificationOrAbort(Request $request, int $notificationId): InAppNotification
    {
        $row = $this->dashboardInAppNotificationQuery($request)
            ->whereKey($notificationId)
            ->first();

        abort_if($row === null, 404);

        return $row;
    }

    /**
     * @return Builder<\App\Models\UserFcmToken>
     */
    protected function dashboardFcmTokenQuery(Request $request): Builder
    {
        $filters = $this->dashboardReportFilterContext(
            $request,
            withUserRoleFilter: true,
            withGuardianFilter: true,
        );

        $query = \App\Models\UserFcmToken::query()->with('user');
        $this->applyDashboardReportFilters($query, $filters, 'user_relation');

        $role = (string) ($filters['filterUserRole'] ?? '');
        if ($role !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($filters): void {
                $this->applyDashboardUserRoleFilter($userQuery, $filters);
            });
        }

        $guardianId = (int) ($filters['filterGuardianId'] ?? 0);
        if ($guardianId > 0) {
            $query->whereHas('user', function (Builder $userQuery) use ($guardianId): void {
                $this->constrainUsersToGuardian($userQuery, $guardianId);
            });
        }

        return $query;
    }

    protected function findDashboardFcmTokenOrAbort(Request $request, int $tokenId): \App\Models\UserFcmToken
    {
        $row = $this->dashboardFcmTokenQuery($request)->whereKey($tokenId)->first();

        abort_if($row === null, 404);

        return $row;
    }
}
