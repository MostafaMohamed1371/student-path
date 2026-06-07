<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Models\Student;
use App\Services\Attendance\StudentDailyTimelineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardStudentDailyTimelineController extends Controller
{
    use ManagesDashboardScoping;

    public function __construct(
        private readonly StudentDailyTimelineService $timelineService,
    ) {}

    public function show(Request $request, Student $student): View
    {
        abort_unless($this->studentVisible($student), 404);

        $date = $request->filled('date')
            ? Carbon::parse((string) $request->query('date'))->startOfDay()
            : now()->startOfDay();

        $timeline = $this->timelineService->timelineForStudent($student, $date);

        $students = Student::query()
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'grade']);

        return view('dashboard.attendance.daily_timeline', [
            'student' => $student->load(['school', 'guardian']),
            'timeline' => $timeline,
            'students' => $students,
            'date' => $date->toDateString(),
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
