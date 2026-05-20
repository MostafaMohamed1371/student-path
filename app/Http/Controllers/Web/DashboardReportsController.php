<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardReportsController extends Controller
{
    use ConstrainsDashboardUserScope;
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

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
        $filters = $this->dashboardReportFilterContext($request);

        $query = InAppNotification::query()
            ->with('user')
            ->latest('in_app_notifications.id');
        $this->applyDashboardReportFilters($query, $filters, 'user_relation');
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
