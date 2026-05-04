<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Requests\Web\StoreDashboardAbsenceRequest;
use App\Http\Requests\Web\UpdateDashboardAbsenceRequest;
use App\Models\Absence;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardAbsenceController extends Controller
{
    use ManagesDashboardScoping;

    public function index(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = Absence::query()
            ->with(['user', 'student'])
            ->latest('absences.id')
            ->whereHas('student', fn (Builder $q) => $this->constrainToScopingSchool($q));

        $absences = $query->paginate($perPage);

        return view('dashboard.absences.index', compact('absences'));
    }

    public function create(): View
    {
        $students = Student::query()
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->orderBy('full_name')
            ->get();

        return view('dashboard.absences.create', compact('students'));
    }

    public function store(StoreDashboardAbsenceRequest $request): RedirectResponse
    {
        $student = Student::query()
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->whereKey((int) $request->validated('student_id'))
            ->firstOrFail();

        $parentUser = $this->resolveParentUserForStudent($student);
        if ($parentUser === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('dashboard.absence_no_parent_user'));
        }

        $validated = $request->validated();
        Absence::query()->create([
            'user_id' => $parentUser->id,
            'student_id' => $student->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('dashboard.absences.index')
            ->with('success', __('dashboard.absence_created'));
    }

    public function edit(Absence $absence): View
    {
        abort_unless($this->absenceVisible($absence), 404);
        $absence->load(['student', 'user']);

        return view('dashboard.absences.edit', compact('absence'));
    }

    public function update(UpdateDashboardAbsenceRequest $request, Absence $absence): RedirectResponse
    {
        abort_unless($this->absenceVisible($absence), 404);

        $validated = $request->validated();
        $start = isset($validated['start_date'])
            ? (string) $validated['start_date']
            : $absence->start_date->toDateString();
        $end = isset($validated['end_date'])
            ? (string) $validated['end_date']
            : $absence->end_date->toDateString();
        if ($end < $start) {
            return redirect()->back()->withInput()->with('error', __('dashboard.absence_invalid_date_range'));
        }

        $absence->fill($validated)->save();

        return redirect()->route('dashboard.absences.index')
            ->with('success', __('dashboard.absence_updated'));
    }

    public function destroy(Absence $absence): RedirectResponse
    {
        abort_unless($this->absenceVisible($absence), 404);
        $absence->delete();

        return redirect()->route('dashboard.absences.index')
            ->with('success', __('dashboard.absence_deleted'));
    }

    private function absenceVisible(Absence $absence): bool
    {
        $student = $absence->student;
        if (! $student) {
            return false;
        }
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }
        $sid = auth()->user()?->scopingSchoolId();

        return $sid !== null && (int) $student->school_id === (int) $sid;
    }

    private function resolveParentUserForStudent(Student $student): ?User
    {
        $byGuardian = User::query()
            ->where('guardian_id', $student->guardian_id)
            ->orderBy('id')
            ->first();
        if ($byGuardian) {
            return $byGuardian;
        }
        $guardian = $student->guardian;
        if ($guardian === null) {
            return null;
        }

        return User::query()->where('phone', $guardian->phone)->orderBy('id')->first();
    }
}
