<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Http\Requests\Web\StoreDashboardStudentRequest;
use App\Http\Requests\Web\UpdateDashboardStudentRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardStudentController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

    public function index(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext($request, withShiftFilter: true);

        $query = Student::query()
            ->with(['school', 'guardian'])
            ->latest('id');
        $this->applyDashboardReportFilters($query, $filters, 'roster_school');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'student_driver_route');
        }
        $this->applyRosterShiftFilter($query, $filters);

        $students = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.students.index', array_merge($filters, [
            'filterAction' => route('dashboard.students.index'),
            'students' => $students,
        ]));
    }

    public function create(): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();

        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first_students');
        }

        $schoolId = (int) old('school_id', auth()->user()?->scopingSchoolId() ?? 0);
        if ($schoolId > 0 && $this->guardiansForSchool($schoolId)->isEmpty()) {
            return redirect()->route('dashboard.guardians.create')
                ->with('error', __('dashboard.create_guardian_first_students'));
        }

        $guardians = $schoolId > 0
            ? $this->guardiansForSchool($schoolId, (int) old('guardian_id', 0))
            : collect();

        return view('dashboard.students.create', [
            'schools' => $schools,
            'guardians' => $guardians,
            'formGuardiansUrl' => route('dashboard.students.form_guardians'),
        ]);
    }

    public function formGuardians(Request $request): JsonResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'include_guardian_id' => ['nullable', 'integer'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $includeId = (int) ($validated['include_guardian_id'] ?? 0);

        return response()->json([
            'guardians' => $this->guardiansForSchool($schoolId, $includeId)
                ->map(fn (Guardian $g): array => [
                    'id' => (int) $g->id,
                    'label' => trim((string) $g->full_name).' ('.(string) $g->phone.')',
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreDashboardStudentRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $validated['school_id']);

        $guardian = Guardian::query()->findOrFail($validated['guardian_id']);
        abort_unless((int) $guardian->school_id === (int) $validated['school_id'], 403);

        $validated['guardian_name'] = $guardian->full_name;
        $validated['guardian_primary_phone'] = $guardian->phone;
        $validated['guardian_backup_phone'] = $guardian->backup_phone;
        $validated['profile_photo'] = $this->storeFile($request->file('profile_photo'), 'profiles');

        $student = DB::transaction(function () use ($validated, $phoneNormalizer): Student {
            $student = Student::query()->create($validated);
            $this->syncStudentUser($student, $phoneNormalizer);

            return $student;
        });

        return redirect()->route('dashboard.students.index')
            ->with('success', __('dashboard.student_created'));
    }

    public function edit(Student $student): View
    {
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $student->school_id);
        $schools = $this->schoolsForRosterForm();
        $schoolId = (int) old('school_id', $student->school_id);
        $guardians = $this->guardiansForSchool(
            $schoolId,
            (int) old('guardian_id', $student->guardian_id),
        );

        return view('dashboard.students.edit', [
            'student' => $student,
            'schools' => $schools,
            'guardians' => $guardians,
            'formGuardiansUrl' => route('dashboard.students.form_guardians'),
        ]);
    }

    public function update(UpdateDashboardStudentRequest $request, Student $student, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) ($validated['school_id'] ?? $student->school_id));

        $guardian = Guardian::query()->findOrFail($validated['guardian_id']);
        abort_unless((int) $guardian->school_id === (int) $validated['school_id'], 403);

        $validated['guardian_name'] = $guardian->full_name;
        $validated['guardian_primary_phone'] = $guardian->phone;
        $validated['guardian_backup_phone'] = $guardian->backup_phone;
        $validated['profile_photo'] = $this->replaceFile($request->file('profile_photo'), $student->profile_photo, 'profiles');

        $student->update($validated);
        $this->syncStudentUser($student->fresh(), $phoneNormalizer);

        return redirect()->route('dashboard.students.index')
            ->with('success', __('dashboard.student_updated'));
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $student->school_id);
        if ($student->profile_photo) {
            Storage::disk('public')->delete($student->profile_photo);
        }

        $student->delete();

        return redirect()->route('dashboard.students.index')
            ->with('success', __('dashboard.student_deleted'));
    }

    private function storeFile(?UploadedFile $file, string $folder): ?string
    {
        return $file?->store($folder, 'public');
    }

    private function replaceFile(?UploadedFile $file, ?string $existing, string $folder): ?string
    {
        if (! $file) {
            return $existing;
        }

        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        return $file->store($folder, 'public');
    }

    private function syncStudentUser(Student $student, PhoneNormalizer $phoneNormalizer): void
    {
        if (! $phoneNormalizer->isValidIraqiMobile((string) $student->student_phone)) {
            return;
        }

        $phone = $phoneNormalizer->normalize((string) $student->student_phone);

        User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $student->full_name,
                'school_id' => $student->school_id,
                'password' => config('dashboard.seed_password'),
                'is_active' => $student->status === 'active',
                'phone_verified_at' => now(),
            ]
        );
    }

    /**
     * @return Collection<int, Guardian>
     */
    private function guardiansForSchool(int $schoolId, int $includeGuardianId = 0): Collection
    {
        if ($schoolId <= 0) {
            return collect();
        }

        $rows = Guardian::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        if ($includeGuardianId > 0 && ! $rows->contains('id', $includeGuardianId)) {
            $extra = Guardian::query()
                ->where('school_id', $schoolId)
                ->whereKey($includeGuardianId)
                ->first();
            if ($extra) {
                $rows = $rows->push($extra)->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
            }
        }

        return $rows;
    }
}
