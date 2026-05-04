<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConstrainsDashboardUserScope;
use App\Http\Requests\Web\StoreDashboardSupportComplaintRequest;
use App\Http\Requests\Web\UpdateDashboardSupportComplaintRequest;
use App\Models\SupportComplaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardSupportComplaintController extends Controller
{
    use ConstrainsDashboardUserScope;

    public function index(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = SupportComplaint::query()
            ->with('user')
            ->latest('support_complaints.id')
            ->whereHas('user', fn (Builder $q) => $this->constrainUsersToDashboardScope($q));

        $complaints = $query->paginate($perPage);

        return view('dashboard.support-complaints.index', compact('complaints'));
    }

    public function create(): View
    {
        $users = User::query()
            ->orderBy('name')
            ->when(! auth()->user()?->is_admin, fn (Builder $q) => $q->tap(fn (Builder $b) => $this->constrainUsersToDashboardScope($b)))
            ->limit(500)
            ->get();

        $categories = config('mobile_legacy_api.support.categories', []);

        return view('dashboard.support-complaints.create', compact('users', 'categories'));
    }

    public function store(StoreDashboardSupportComplaintRequest $request): RedirectResponse
    {
        abort_unless($this->userIdVisibleInDashboardScope((int) $request->validated('user_id')), 403);

        $paths = [];
        if ($request->hasFile('attachment')) {
            $paths[] = $request->file('attachment')->store('support-complaints', 'local');
        }

        $validated = $request->validated();
        $complaint = SupportComplaint::query()->create([
            'user_id' => (int) $validated['user_id'],
            'category_id' => $validated['category_id'],
            'details' => $validated['details'],
            'attachments' => $paths !== [] ? $paths : null,
            'complaint_number' => null,
            'status' => 'RECEIVED',
        ]);

        $complaintNumber = '#CMP-'.now()->format('Y').'-'.str_pad((string) $complaint->id, 4, '0', STR_PAD_LEFT);
        $complaint->forceFill(['complaint_number' => $complaintNumber])->save();

        return redirect()->route('dashboard.support_complaints.show', $complaint)
            ->with('success', __('dashboard.support_complaint_created'));
    }

    public function show(SupportComplaint $complaint): View
    {
        $complaint->load('user');
        abort_unless($this->userIdVisibleInDashboardScope((int) $complaint->user_id), 404);

        return view('dashboard.support-complaints.show', compact('complaint'));
    }

    public function edit(SupportComplaint $complaint): View
    {
        $complaint->load('user');
        abort_unless($this->userIdVisibleInDashboardScope((int) $complaint->user_id), 404);
        $categories = config('mobile_legacy_api.support.categories', []);

        return view('dashboard.support-complaints.edit', compact('complaint', 'categories'));
    }

    public function update(UpdateDashboardSupportComplaintRequest $request, SupportComplaint $complaint): RedirectResponse
    {
        abort_unless($this->userIdVisibleInDashboardScope((int) $complaint->user_id), 404);
        $complaint->update($request->validated());

        return redirect()
            ->route('dashboard.support_complaints.show', $complaint)
            ->with('success', __('dashboard.support_complaint_updated'));
    }

    public function destroy(SupportComplaint $complaint): RedirectResponse
    {
        abort_unless($this->userIdVisibleInDashboardScope((int) $complaint->user_id), 404);
        $complaint->delete();

        return redirect()->route('dashboard.support_complaints.index')
            ->with('success', __('dashboard.support_complaint_deleted'));
    }
}
