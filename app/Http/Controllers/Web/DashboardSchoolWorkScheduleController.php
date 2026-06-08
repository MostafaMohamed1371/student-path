<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Trips\DriverShiftResolver;
use App\Support\SchoolWorkDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardSchoolWorkScheduleController extends Controller
{
    public function show(): View
    {
        $school = $this->schoolForStaff();

        return view('dashboard.school-work-schedule.show', [
            'school' => $school,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $this->schoolForStaff();
        $payload = $this->validatedWorkSchedule($request);

        $school->update($payload);

        return redirect()
            ->route('dashboard.school_work_schedule.show')
            ->with('success', __('dashboard.school_work_schedule_saved'));
    }

    private function schoolForStaff(): School
    {
        $this->abortUnlessSchoolStaffAccount();

        $schoolId = (int) auth()->user()->school_id;

        return School::query()->findOrFail($schoolId);
    }

    private function abortUnlessSchoolStaffAccount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user !== null
            && $user->school_id !== null
            && ! $user->is_admin,
            403,
        );
    }

    /** @return array<string, mixed> */
    private function validatedWorkSchedule(Request $request): array
    {
        $payload = $request->validate([
            'shift_period' => ['required', 'string', Rule::in([
                DriverShiftResolver::MORNING,
                DriverShiftResolver::EVENING,
                'BOTH',
            ])],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', Rule::in(SchoolWorkDay::keys())],
            'work_time_from' => ['nullable', 'date_format:H:i'],
            'work_time_to' => ['nullable', 'date_format:H:i', 'after:work_time_from'],
            'evening_work_time_from' => ['nullable', 'date_format:H:i', 'required_if:shift_period,BOTH'],
            'evening_work_time_to' => ['nullable', 'date_format:H:i', 'required_if:shift_period,BOTH', 'after:evening_work_time_from'],
        ]);

        $shiftPeriod = (string) $payload['shift_period'];

        $workDays = collect($payload['work_days'] ?? [])
            ->map(fn ($day) => strtolower((string) $day))
            ->filter(fn ($day) => in_array($day, SchoolWorkDay::keys(), true))
            ->unique()
            ->values()
            ->all();

        $eveningFrom = null;
        $eveningTo = null;
        if ($shiftPeriod === 'BOTH') {
            $eveningFrom = $payload['evening_work_time_from'] ?? null;
            $eveningTo = $payload['evening_work_time_to'] ?? null;
        }

        return [
            'shift_period' => $shiftPeriod,
            'work_days' => $workDays !== [] ? $workDays : null,
            'work_time_from' => filled($payload['work_time_from'] ?? null) ? $payload['work_time_from'] : null,
            'work_time_to' => filled($payload['work_time_to'] ?? null) ? $payload['work_time_to'] : null,
            'evening_work_time_from' => filled($eveningFrom) ? $eveningFrom : null,
            'evening_work_time_to' => filled($eveningTo) ? $eveningTo : null,
        ];
    }
}
