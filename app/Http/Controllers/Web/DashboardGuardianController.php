<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Requests\Web\StoreDashboardGuardianRequest;
use App\Http\Requests\Web\UpdateDashboardGuardianRequest;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardGuardianController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $guardians = Guardian::query()
            ->with('school')
            ->withCount('students')
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->latest('id')
            ->paginate(12);

        return view('dashboard.guardians.index', compact('guardians'));
    }

    public function create(): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first_guardians');
        }

        return view('dashboard.guardians.create', compact('schools'));
    }

    public function store(StoreDashboardGuardianRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $validated['school_id']);

        $guardian = Guardian::query()->create($validated);
        $this->syncGuardianUser($guardian, $phoneNormalizer);

        return redirect()->route('dashboard.guardians.index')
            ->with('success', __('dashboard.guardian_created'));
    }

    public function edit(Guardian $guardian): View
    {
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $guardian->school_id);
        $schools = $this->schoolsForRosterForm();

        return view('dashboard.guardians.edit', compact('guardian', 'schools'));
    }

    public function update(UpdateDashboardGuardianRequest $request, Guardian $guardian, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) ($validated['school_id'] ?? $guardian->school_id));

        $guardian->update($validated);
        $this->syncGuardianUser($guardian->fresh(), $phoneNormalizer);

        return redirect()->route('dashboard.guardians.index')
            ->with('success', __('dashboard.guardian_updated'));
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $guardian->school_id);
        $guardian->delete();

        return redirect()->route('dashboard.guardians.index')
            ->with('success', __('dashboard.guardian_deleted'));
    }

    private function syncGuardianUser(Guardian $guardian, PhoneNormalizer $phoneNormalizer): void
    {
        if (! $phoneNormalizer->isValidIraqiMobile((string) $guardian->phone)) {
            return;
        }

        $phone = $phoneNormalizer->normalize((string) $guardian->phone);

        User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $guardian->full_name,
                'school_id' => $guardian->school_id,
                'password' => config('dashboard.seed_password'),
                'is_active' => $guardian->status === 'active',
                'phone_verified_at' => now(),
            ]
        );
    }
}
