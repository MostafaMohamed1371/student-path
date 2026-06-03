<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Enums\PhoneAccountType;
use App\Http\Requests\Web\StoreDashboardUserRequest;
use App\Http\Requests\Web\UpdateDashboardUserRequest;
use App\Models\School;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardUserController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

    public function index(Request $request): View
    {
        abort_unless($this->isAdmin(), 403);
        $filters = $this->dashboardReportFilterContext(
            $request,
            withStudentFilter: true,
            withUserRoleFilter: true,
            withGuardianFilter: true,
        );

        $query = User::query()
            ->with(['school', 'driver', 'guardian'])
            ->latest('id');
        $this->applyDashboardReportFilters($query, $filters, 'user_school');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'user_driver_direct');
        }
        if ((int) $filters['filterStudentId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'user_student');
        }
        if ((int) $filters['filterGuardianId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'user_guardian');
        }
        $this->applyDashboardUserRoleFilter($query, $filters);

        $users = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.users.index', array_merge($filters, [
            'filterAction' => route('dashboard.users.index'),
            'users' => $users,
        ]));
    }

    public function create(): View
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();

        return view('dashboard.users.create', compact('schools'));
    }

    public function store(StoreDashboardUserRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validated();
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('profiles', 'public')
            : null;

        $user = User::query()->create([
            'name' => $validated['name'] ?? null,
            'school_id' => $validated['school_id'],
            'image' => $imagePath,
            'phone' => $phoneNormalizer->normalize($validated['phone']),
            'phone_account_type' => ($validated['is_admin'] ?? false)
                ? PhoneAccountType::Admin->value
                : PhoneAccountType::School->value,
            'city' => $validated['city'] ?? null,
            'licence_number' => $validated['licence_number'] ?? null,
            'votes' => $validated['votes'],
            'rate' => $validated['rate'],
            'is_verified' => $validated['is_verified'] ?? false,
            'is_admin' => $validated['is_admin'] ?? false,
            'password' => $validated['password'],
            'is_active' => $validated['is_active'] ?? true,
            'phone_verified_at' => now(),
        ]);

        return redirect()->route('dashboard.users.index')->with('success', __('dashboard.user_created'));
    }

    public function edit(User $user): View
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();

        return view('dashboard.users.edit', compact('user', 'schools'));
    }

    public function update(UpdateDashboardUserRequest $request, User $user, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'] ?? null,
            'school_id' => $validated['school_id'],
            'phone' => $phoneNormalizer->normalize($validated['phone']),
            'phone_account_type' => ($validated['is_admin'] ?? false)
                ? PhoneAccountType::Admin->value
                : PhoneAccountType::School->value,
            'city' => $validated['city'] ?? null,
            'licence_number' => $validated['licence_number'] ?? null,
            'votes' => $validated['votes'],
            'rate' => $validated['rate'],
            'is_verified' => $validated['is_verified'] ?? false,
            'is_admin' => $validated['is_admin'] ?? false,
            'is_active' => $validated['is_active'] ?? false,
        ];

        if ($request->hasFile('image')) {
            $payload['image'] = $request->file('image')->store('profiles', 'public');

            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
        }

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        return redirect()->route('dashboard.users.index')->with('success', __('dashboard.user_updated'));
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        if (auth()->id() === $user->id) {
            return redirect()->route('dashboard.users.index')->with('error', __('dashboard.cannot_delete_self'));
        }

        $user->delete();

        return redirect()->route('dashboard.users.index')->with('success', __('dashboard.user_deleted'));
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}
