<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Models\Student;
use App\Services\Attendance\StudentAttendanceScheduleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardStudentAttendanceController extends Controller
{
    use ManagesDashboardScoping;

    public function __construct(
        private readonly StudentAttendanceScheduleService $scheduleService,
    ) {}

    public function show(Request $request, Student $student): View
    {
        abort_unless($this->studentVisible($student), 404);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $schedule = $this->scheduleService->scheduleForStudent($student, $year, $month, 20);

        $students = Student::query()
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'grade']);

        return view('dashboard.attendance.schedule', [
            'student' => $student->load(['school', 'guardian']),
            'schedule' => $schedule,
            'students' => $students,
            'year' => $year,
            'month' => $month,
        ]);
    }

    private function studentVisible(Student $student): bool
    {
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }
        $sid = auth()->user()?->scopingSchoolId();

        return $sid !== null && (int) $student->school_id === (int) $sid;
    }
}
