<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AppliesDashboardInAppNotificationFilters;
use App\Http\Controllers\Web\Concerns\ConstrainsDashboardUserScope;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Models\DelayAlert;
use App\Models\InAppNotification;
use App\Models\SosAlert;
use App\Models\TripHistory;
use App\Models\WalletQicardPayment;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardReportsController extends Controller
{
    use AppliesDashboardInAppNotificationFilters;
    use ConstrainsDashboardUserScope;
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

    public function notificationsHub(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext($request);
        $since = now()->subDays(7);

        $inAppBase = InAppNotification::query();
        $this->applyDashboardReportFilters($inAppBase, $filters, 'user_relation');

        $delayBase = DelayAlert::query();
        $this->applyDashboardReportFilters($delayBase, $filters, 'delay_sos_alert');

        $sosBase = SosAlert::query();
        $this->applyDashboardReportFilters($sosBase, $filters, 'delay_sos_alert');

        $tripsBase = TripHistory::query()->where('status', 'COMPLETED');
        $this->applyDashboardReportFilters($tripsBase, $filters, 'trip_history');

        $stats = [
            'in_app_7d' => (clone $inAppBase)->where('in_app_notifications.created_at', '>=', $since)->count(),
            'in_app_unread' => (clone $inAppBase)->whereNull('read_at')->count(),
            'delay_7d' => (clone $delayBase)->where('delay_alerts.created_at', '>=', $since)->count(),
            'sos_active' => (clone $sosBase)->where('status', 'TRIGGERED')->whereNull('stopped_at')->count(),
            'trips_completed_7d' => (clone $tripsBase)->where('trip_histories.updated_at', '>=', $since)->count(),
        ];

        $recentQuery = InAppNotification::query()
            ->with('user')
            ->latest('in_app_notifications.id')
            ->limit(15);
        $this->applyDashboardReportFilters($recentQuery, $filters, 'user_relation');
        $recentNotifications = $recentQuery->get();

        $typeCountsQuery = (clone $inAppBase)
            ->where('in_app_notifications.created_at', '>=', $since)
            ->select('data->type as notification_type', DB::raw('count(*) as aggregate'))
            ->groupBy('notification_type')
            ->orderByDesc('aggregate');
        $typeCounts7d = $typeCountsQuery->get()
            ->map(fn ($row): array => [
                'type' => (string) ($row->notification_type ?? 'unknown'),
                'count' => (int) $row->aggregate,
            ])
            ->filter(fn (array $row): bool => $row['type'] !== '' && $row['type'] !== 'unknown')
            ->values()
            ->all();

        return view('dashboard.notifications-hub', array_merge($filters, [
            'filterAction' => route('dashboard.notifications.hub'),
            'stats' => $stats,
            'recentNotifications' => $recentNotifications,
            'typeCounts7d' => $typeCounts7d,
        ]));
    }

    public function payments(Request $request): View
    {
        $perPage = $this->dashboardListPerPage();
        $filters = $this->dashboardReportFilterContext($request);

        $txQuery = WalletTransaction::query()
            ->with(['wallet.user'])
            ->latest('wallet_transactions.id');
        $this->applyDashboardReportFilters($txQuery, $filters, 'user_relation');
        $transactions = $txQuery->paginate($perPage)->withQueryString();

        $qicard = null;
        if (Schema::hasTable('wallet_qicard_payments')) {
            $qicardQuery = WalletQicardPayment::query()
                ->with('user')
                ->latest('wallet_qicard_payments.id');
            $this->applyDashboardReportFilters($qicardQuery, $filters, 'user_relation');
            $qicard = $qicardQuery->paginate($perPage, ['*'], 'qicard_page')->withQueryString();
        }

        return view('dashboard.payments', array_merge($filters, [
            'filterAction' => route('dashboard.payments'),
            'transactions' => $transactions,
            'qicardPayments' => $qicard,
        ]));
    }

    public function notifications(Request $request): View
    {
        $perPage = $this->dashboardListPerPage();
        $filters = array_merge(
            $this->dashboardReportFilterContext(
                $request,
                withUserRoleFilter: true,
                withGuardianFilter: true,
            ),
            $this->dashboardInAppNotificationExtraFilters($request),
        );

        $query = InAppNotification::query()
            ->with('user')
            ->latest('in_app_notifications.id');
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
        $notifications = $query->paginate($perPage)->withQueryString();

        return view('dashboard.in-app-notifications', array_merge($filters, [
            'filterAction' => route('dashboard.in_app_notifications'),
            'notifications' => $notifications,
        ]));
    }

    public function delayAlerts(Request $request): View
    {
        $perPage = $this->dashboardListPerPage();
        $filters = $this->dashboardReportFilterContext($request);

        $query = DelayAlert::query()
            ->with(['tripHistory', 'driver', 'user'])
            ->latest('delay_alerts.id');
        $this->applyDashboardReportFilters($query, $filters, 'delay_sos_alert');
        $alerts = $query->paginate($perPage)->withQueryString();

        return view('dashboard.delay-alerts', array_merge($filters, [
            'filterAction' => route('dashboard.delay_alerts'),
            'alerts' => $alerts,
        ]));
    }

    public function sosAlerts(Request $request): View
    {
        $perPage = $this->dashboardListPerPage();
        $filters = $this->dashboardReportFilterContext($request);

        $query = SosAlert::query()
            ->with(['tripHistory', 'driver', 'user'])
            ->latest('sos_alerts.id');
        $this->applyDashboardReportFilters($query, $filters, 'delay_sos_alert');
        $alerts = $query->paginate($perPage)->withQueryString();

        return view('dashboard.sos-alerts', array_merge($filters, [
            'filterAction' => route('dashboard.sos_alerts'),
            'alerts' => $alerts,
        ]));
    }

    public function tripFinalizationReports(Request $request): View
    {
        $perPage = $this->dashboardListPerPage();
        $filters = $this->dashboardReportFilterContext($request);

        $query = TripHistory::query()
            ->with(['driver', 'school'])
            ->where('status', 'COMPLETED')
            ->latest('trip_histories.id');
        $this->applyDashboardReportFilters($query, $filters, 'trip_history');
        $trips = $query->paginate($perPage)->withQueryString();

        return view('dashboard.trip-finalization-reports', array_merge($filters, [
            'filterAction' => route('dashboard.trip_finalization_reports'),
            'trips' => $trips,
        ]));
    }
}
