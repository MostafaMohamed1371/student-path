<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Http\Requests\Web\StoreDashboardGuardianRequest;
use App\Http\Requests\Web\UpdateDashboardGuardianRequest;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Guardian\GuardianHomeLocationSync;
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
            'homeLocation' => null,
        ]);
    }

    public function lookupByIdCard(
        Request $request,
        DashboardIdCardRegistry $idCardRegistry,
        GuardianSchoolProvisioner $provisioner,
        GuardianHomeLocationSync $homeLocationSync,
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
                    'guardian' => $this->guardianLookupPayload($atSchool, $homeLocationSync),
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
            'guardian' => $this->guardianLookupPayload($source, $homeLocationSync),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianLookupPayload(Guardian $guardian, GuardianHomeLocationSync $homeLocationSync): array
    {
        $payload = [
            'full_name' => (string) $guardian->full_name,
            'phone' => (string) $guardian->phone,
            'backup_phone' => (string) ($guardian->backup_phone ?? ''),
            'id_card_number' => (string) ($guardian->id_card_number ?? ''),
            'status' => (string) $guardian->status,
        ];

        $payload = array_merge($payload, $homeLocationSync->homeLocationFieldsForGuardian($guardian));

        return $payload;
    }

    public function store(
        StoreDashboardGuardianRequest $request,
        PhoneNormalizer $phoneNormalizer,
        GuardianHomeLocationSync $homeLocationSync,
    ): RedirectResponse {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $validated['school_id']);

        $guardian = Guardian::query()->create($this->guardianAttributesFromValidated($validated));
        $user = $this->syncGuardianUser($guardian, app(DashboardPhoneUserProvisioner::class));
        $this->syncGuardianHomeLocation($user, $validated, $homeLocationSync);

        return redirect()->route('dashboard.guardians.index')
            ->with('success', __('dashboard.guardian_created'));
    }

    public function edit(Guardian $guardian): View
    {
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $guardian->school_id);
        $schools = $this->schoolsForRosterForm();

        $homeLocation = app(GuardianHomeLocationSync::class)->homeLocationForGuardian($guardian);

        return view('dashboard.guardians.edit', compact('guardian', 'schools', 'homeLocation'));
    }

    public function update(
        UpdateDashboardGuardianRequest $request,
        Guardian $guardian,
        PhoneNormalizer $phoneNormalizer,
        GuardianHomeLocationSync $homeLocationSync,
    ): RedirectResponse {
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) ($validated['school_id'] ?? $guardian->school_id));

        $guardian->update($this->guardianAttributesFromValidated($validated));
        $user = $this->syncGuardianUser($guardian->fresh(), app(DashboardPhoneUserProvisioner::class));
        $this->syncGuardianHomeLocation($user, $validated, $homeLocationSync);

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

    private function syncGuardianUser(Guardian $guardian, DashboardPhoneUserProvisioner $provisioner): User
    {
        return $provisioner->upsertGuardian(
            $guardian,
            (string) $guardian->phone,
            (string) $guardian->full_name,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function guardianAttributesFromValidated(array $validated): array
    {
        return collect($validated)->only([
            'school_id',
            'full_name',
            'phone',
            'backup_phone',
            'id_card_number',
            'status',
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncGuardianHomeLocation(
        User $user,
        array $validated,
        GuardianHomeLocationSync $homeLocationSync,
    ): void {
        $lat = array_key_exists('home_latitude', $validated) && $validated['home_latitude'] !== null
            ? (float) $validated['home_latitude']
            : null;
        $lng = array_key_exists('home_longitude', $validated) && $validated['home_longitude'] !== null
            ? (float) $validated['home_longitude']
            : null;
        $address = isset($validated['home_formatted_address']) && is_string($validated['home_formatted_address'])
            ? trim($validated['home_formatted_address'])
            : null;
        $district = isset($validated['home_district_area']) && is_string($validated['home_district_area'])
            ? trim($validated['home_district_area'])
            : null;
        $landmark = isset($validated['home_nearest_landmark']) && is_string($validated['home_nearest_landmark'])
            ? trim($validated['home_nearest_landmark'])
            : null;

        $homeLocationSync->syncForUser(
            $user,
            $lat,
            $lng,
            $address !== '' ? $address : null,
            $district !== '' ? $district : null,
            $landmark !== '' ? $landmark : null,
        );
    }
}
