<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardIraqLocationFilters;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\District;
use App\Models\School;
use App\Services\Phone\DashboardPhoneUserProvisioner;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardSchoolController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardIraqLocationFilters;
    use ProvidesDashboardSchoolDriverFilters;

    public function index(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext($request, withStudentFilter: true);

        $query = School::query()
            ->withCount(['buses'])
            ->latest('id');
        $this->applyDashboardReportFilters($query, $filters, 'school_row');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'school_driver');
        }
        if ((int) $filters['filterStudentId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'school_student');
        }

        $schools = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.schools.index', array_merge($filters, [
            'filterAction' => route('dashboard.schools.index'),
            'schools' => $schools,
        ]));
    }

    public function create(): View
    {
        abort_unless($this->isAdmin(), 403);

        return view('dashboard.schools.create', [
            'locationForm' => $this->iraqLocationFormContext(
                (int) old('district_id', 0),
                (int) old('area_id', 0),
                (int) old('neighborhood_id', 0),
            ),
        ]);
    }

    public function store(Request $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $payload = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        $school = School::query()->create($payload);
        $this->syncSchoolAdminUser($school, $phoneNormalizer, app(DashboardPhoneUserProvisioner::class));

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_created'));
    }

    public function edit(School $school): View
    {
        abort_unless($this->isAdmin(), 403);

        return view('dashboard.schools.edit', [
            'school' => $school,
            'locationForm' => $this->iraqLocationFormContext(
                (int) old('district_id', $school->district_id ?? 0),
                (int) old('area_id', $school->area_id ?? 0),
                (int) old('neighborhood_id', $school->neighborhood_id ?? 0),
            ),
        ]);
    }

    public function update(Request $request, School $school, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $payload = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        $school->update($payload);
        $this->syncSchoolAdminUser($school->fresh(), $phoneNormalizer, app(DashboardPhoneUserProvisioner::class));

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_updated'));
    }

    public function destroy(School $school): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $school->delete();

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_deleted'));
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'neighborhood_id' => ['required', 'integer', 'exists:neighborhoods,id'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'string', 'max:32'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'authorized_person_name' => ['nullable', 'string', 'max:255'],
            'authorized_person_phone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'complaints_support_phone' => ['nullable', 'string', 'min:3', 'max:32'],
            'complaints_support_whatsapp' => ['nullable', 'string', 'min:3', 'max:32'],
            'complaints_support_hours' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ]);

        return $this->applySchoolIraqLocation($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applySchoolIraqLocation(array $validated): array
    {
        $resolved = app(\App\Services\Locations\IraqLocationAttributeResolver::class)->resolve($validated);
        $validated['district_id'] = $resolved['district_id'];
        $validated['area_id'] = $resolved['area_id'];
        $validated['neighborhood_id'] = $resolved['neighborhood_id'];

        $governorate = $resolved['district_id']
            ? District::query()->find($resolved['district_id'])
            : null;
        $area = $resolved['area_id']
            ? Area::query()->find($resolved['area_id'])
            : null;

        $validated['province'] = $governorate?->name ?? '';
        $validated['district'] = $area?->name ?? '';

        return $validated;
    }

    private function syncSchoolAdminUser(
        School $school,
        PhoneNormalizer $phoneNormalizer,
        DashboardPhoneUserProvisioner $provisioner,
    ): void {
        if (! $school->admin_phone || ! $phoneNormalizer->isValidIraqiMobile((string) $school->admin_phone)) {
            return;
        }

        $name = $school->principal_name ?: $school->name_en ?: $school->name_ar;
        $provisioner->upsertSchoolStaff($school, (string) $school->admin_phone, (string) $name);
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}
