<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConstrainsDashboardUserScope;
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

    public function payments(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $txQuery = WalletTransaction::query()
            ->with(['wallet.user'])
            ->whereHas('wallet.user', fn (Builder $q) => $this->constrainUsersToDashboardScope($q))
            ->latest('wallet_transactions.id');

        $transactions = $txQuery->paginate($perPage);

        $qicard = null;
        if (Schema::hasTable('wallet_qicard_payments')) {
            $qicardQuery = WalletQicardPayment::query()
                ->with('user')
                ->whereHas('user', fn (Builder $q) => $this->constrainUsersToDashboardScope($q))
                ->latest('wallet_qicard_payments.id');

            $qicard = $qicardQuery->paginate($perPage, ['*'], 'qicard_page');
        }

        return view('dashboard.payments', [
            'transactions' => $transactions,
            'qicardPayments' => $qicard,
        ]);
    }

    public function notifications(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = InAppNotification::query()
            ->with('user')
            ->whereHas('user', fn (Builder $q) => $this->constrainUsersToDashboardScope($q))
            ->latest('in_app_notifications.id');

        $notifications = $query->paginate($perPage);

        return view('dashboard.in-app-notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function delayAlerts(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = DelayAlert::query()
            ->with(['tripHistory', 'driver', 'user'])
            ->latest('delay_alerts.id');

        if (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('tripHistory', fn (Builder $q) => $q->where('school_id', $sid));
            }
        }

        $alerts = $query->paginate($perPage);

        return view('dashboard.delay-alerts', [
            'alerts' => $alerts,
        ]);
    }

    public function sosAlerts(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = SosAlert::query()
            ->with(['tripHistory', 'driver', 'user'])
            ->latest('sos_alerts.id');

        if (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('tripHistory', fn (Builder $q) => $q->where('school_id', $sid));
            }
        }

        $alerts = $query->paginate($perPage);

        return view('dashboard.sos-alerts', [
            'alerts' => $alerts,
        ]);
    }

    public function tripFinalizationReports(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = TripHistory::query()
            ->with(['driver', 'school'])
            ->where('status', 'COMPLETED')
            ->latest('trip_histories.id');

        if (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->where('school_id', $sid);
            }
        }

        $trips = $query->paginate($perPage);

        return view('dashboard.trip-finalization-reports', [
            'trips' => $trips,
        ]);
    }
}
