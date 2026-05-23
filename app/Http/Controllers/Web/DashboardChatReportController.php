<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardChatReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureSupportStaff();

        $status = $request->query('status');
        $allowed = ['pending', 'reviewed', 'resolved'];

        $query = ChatReport::query()
            ->with([
                'reporter:id,name,phone',
                'conversation.user:id,name,phone',
            ])
            ->latest('id');

        if (is_string($status) && in_array($status, $allowed, true)) {
            $query->where('status', $status);
        }

        $reports = $query->paginate(30)->withQueryString();

        return view('dashboard.chat-reports.index', [
            'reports' => $reports,
            'statusFilter' => $status,
        ]);
    }

    public function updateStatus(Request $request, ChatReport $report): RedirectResponse
    {
        $this->ensureSupportStaff();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,reviewed,resolved'],
        ]);

        $report->update(['status' => $validated['status']]);

        return back()->with('success', __('dashboard.chat_report_status_updated'));
    }

    private function ensureSupportStaff(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
