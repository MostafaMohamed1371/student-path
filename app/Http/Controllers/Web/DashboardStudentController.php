<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDashboardStudentRequest;
use App\Http\Requests\Web\UpdateDashboardStudentRequest;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardStudentController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $students = Student::query()
            ->with(['school', 'guardian'])
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->latest('id')
            ->paginate(12);

        return view('dashboard.students.index', compact('students'));
    }

    public function create(): View|RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();
        $guardians = Guardian::query()->orderBy('full_name')->get();

        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first_students');
        }

        if ($guardians->isEmpty()) {
            return redirect()->route('dashboard.guardians.create')
                ->with('error', __('dashboard.create_guardian_first_students'));
        }

        return view('dashboard.students.create', compact('schools', 'guardians'));
    }

    public function store(StoreDashboardStudentRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validated();
        $guardian = Guardian::query()->findOrFail($validated['guardian_id']);
        $validated['guardian_name'] = $guardian->full_name;
        $validated['guardian_primary_phone'] = $guardian->phone;
        $validated['guardian_backup_phone'] = $guardian->backup_phone;
        $validated['profile_photo'] = $this->storeFile($request->file('profile_photo'), 'profiles');

        $student = Student::query()->create($validated);
        $this->syncStudentUser($student, $phoneNormalizer);

        return redirect()->route('dashboard.students.index')
            ->with('success', __('dashboard.student_created'));
    }

    public function edit(Student $student): View
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();
        $guardians = Guardian::query()->orderBy('full_name')->get();

        return view('dashboard.students.edit', compact('student', 'schools', 'guardians'));
    }

    public function update(UpdateDashboardStudentRequest $request, Student $student, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validated();
        $guardian = Guardian::query()->findOrFail($validated['guardian_id']);
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
        abort_unless($this->isAdmin(), 403);
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

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}
