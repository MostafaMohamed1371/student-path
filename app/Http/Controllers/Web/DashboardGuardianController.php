<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Http\Requests\Web\StoreDashboardGuardianRequest;
use App\Http\Requests\Web\UpdateDashboardGuardianRequest;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Guardian\GuardianSchoolProvisioner;
use App\Services\IdCard\DashboardIdCardRegistry;
use App\Services\Phone\DashboardPhoneUserProvisioner;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardGuardianController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

    public function index(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext($request);

        $query = Guardian::query()
            ->with('school')
            ->withCount('students')
            ->latest('id');
        $this->applyDashboardReportFilters($query, $filters, 'roster_school');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'guardian_driver_route');
        }

        $guardians = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.guardians.index', array_merge($filters, [
            'filterAction' => route('dashboard.guardians.index'),
            'guardians' => $guardians,
        ]));
    }

    public function create(): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first_guardians');
        }

        return view('dashboard.guardians.create', [
            'schools' => $schools,
            'guardianLookupUrl' => route('dashboard.guardians.lookup_by_id_card'),
        ]);
    }

    public function lookupByIdCard(
        Request $request,
        DashboardIdCardRegistry $idCardRegistry,
        GuardianSchoolProvisioner $provisioner,
    ): JsonResponse {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'id_card_number' => ['required', 'string', 'max:64'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = (int) ($validated['school_id'] ?? 0);
        if ($schoolId > 0) {
            $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);
        }

        $normalized = $idCardRegistry->normalize($validated['id_card_number']);
        if ($normalized === null) {
            return response()->json(['found' => false]);
        }

        if ($schoolId > 0) {
            $atSchool = $provisioner->findForSchoolByIdCard($schoolId, $normalized);
            if ($atSchool !== null) {
                return response()->json([
                    'found' => true,
                    'already_at_school' => true,
                    'guardian' => $this->guardianLookupPayload($atSchool),
                ]);
            }
        }

        $source = $provisioner->findAnyByIdCard($normalized);
        if ($source === null) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'already_at_school' => false,
            'guardian' => $this->guardianLookupPayload($source),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianLookupPayload(Guardian $guardian): array
    {
        return [
            'full_name' => (string) $guardian->full_name,
            'phone' => (string) $guardian->phone,
            'backup_phone' => (string) ($guardian->backup_phone ?? ''),
            'id_card_number' => (string) ($guardian->id_card_number ?? ''),
            'status' => (string) $guardian->status,
        ];
    }

    public function store(StoreDashboardGuardianRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $validated['school_id']);

        $guardian = Guardian::query()->create($validated);
        $this->syncGuardianUser($guardian, app(DashboardPhoneUserProvisioner::class));

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
        $this->syncGuardianUser($guardian->fresh(), app(DashboardPhoneUserProvisioner::class));

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

    private function syncGuardianUser(Guardian $guardian, DashboardPhoneUserProvisioner $provisioner): void
    {
        $provisioner->upsertGuardian(
            $guardian,
            (string) $guardian->phone,
            (string) $guardian->full_name,
        );
    }
}
