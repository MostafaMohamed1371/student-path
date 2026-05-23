<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\QueriesDashboardInAppNotifications;
use App\Models\InAppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardNotificationStaffController extends Controller
{
    use QueriesDashboardInAppNotifications;

    public function show(Request $request, InAppNotification $notification): View
    {
        $row = $this->findDashboardInAppNotificationOrAbort($request, (int) $notification->id);
        $row->load('user');

        return view('dashboard.in-app-notification-show', [
            'notification' => $row,
            'listQuery' => $request->only([
                'school_id', 'driver_id', 'guardian_id', 'user_role',
                'notification_type', 'unread_only', 'page',
            ]),
        ]);
    }

    public function markRead(Request $request, InAppNotification $notification): RedirectResponse
    {
        $this->findDashboardInAppNotificationOrAbort($request, (int) $notification->id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        $redirect = $request->boolean('return_to_show')
            ? route('dashboard.in_app_notifications.show', array_merge(
                ['notification' => $notification->id],
                $request->only(['school_id', 'driver_id', 'guardian_id', 'user_role', 'notification_type', 'unread_only', 'page']),
            ))
            : $this->redirectBackToList($request);

        return redirect()
            ->to($redirect)
            ->with('success', __('dashboard.notification_staff_marked_read'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $updated = $this->dashboardInAppNotificationQuery($request)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()
            ->to($this->redirectBackToList($request))
            ->with('success', __('dashboard.notification_staff_marked_all_read', ['count' => $updated]));
    }

    public function fcmTokens(Request $request): View
    {
        $perPage = $this->dashboardListPerPage();
        $filters = $this->dashboardReportFilterContext(
            $request,
            withUserRoleFilter: true,
            withGuardianFilter: true,
        );

        $tokens = $this->dashboardFcmTokenQuery($request)
            ->latest('user_fcm_tokens.id')
            ->paginate($perPage)
            ->withQueryString();

        return view('dashboard.fcm-tokens', array_merge($filters, [
            'filterAction' => route('dashboard.fcm_tokens.index'),
            'tokens' => $tokens,
        ]));
    }

    public function destroyFcmToken(Request $request, int $fcmToken): RedirectResponse
    {
        $row = $this->findDashboardFcmTokenOrAbort($request, $fcmToken);
        $row->delete();

        return redirect()
            ->route('dashboard.fcm_tokens.index', $request->only([
                'school_id', 'driver_id', 'guardian_id', 'user_role',
            ]))
            ->with('success', __('dashboard.fcm_token_staff_removed'));
    }

    private function redirectBackToList(Request $request): string
    {
        $params = $request->only([
            'school_id', 'driver_id', 'guardian_id', 'user_role',
            'notification_type', 'unread_only', 'page',
        ]);

        return route('dashboard.in_app_notifications', $params);
    }
}
