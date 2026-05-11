<?php

namespace App\Http\Controllers\Web;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Services\Trips\DriverTripModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardTripController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $trips = TripHistory::query()
            ->with(['school', 'driver'])
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->orderByDesc('start_time')
            ->paginate(12);

        return view('dashboard.trips.index', compact('trips'));
    }

    public function show(TripHistory $trip, DriverTripModuleService $driverTripModule): View
    {
        abort_unless($this->tripHistoryVisible($trip), 404);
        $trip->load(['school', 'driver', 'tripHistoryStudents.student']);

        $tripDetail = $driverTripModule->tripDetailPayload($trip);

        return view('dashboard.trips.show', compact('trip', 'tripDetail'));
    }

    public function create(): View|RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first');
        }

        $drivers = Driver::query()->orderBy('first_name')->orderBy('last_name')->get();
        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $students = Student::query()->orderBy('full_name')->limit(1000)->get();

        return view('dashboard.trips.create', compact('schools', 'drivers', 'tripTypes', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validate($this->rules());
        $studentIds = $validated['student_ids'] ?? [];
        unset($validated['student_ids']);

        $trip = TripHistory::query()->create($validated);

        if ($studentIds !== []) {
            $this->syncTripStudentsForSchool($trip, $studentIds, (int) $trip->school_id);
        }

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_created'));
    }

    public function edit(TripHistory $trip): View
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();
        $drivers = Driver::query()->orderBy('first_name')->orderBy('last_name')->get();
        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $students = Student::query()->orderBy('full_name')->limit(1000)->get();
        $selectedStudentIds = $trip->tripHistoryStudents()->pluck('student_id')->all();

        return view('dashboard.trips.edit', compact('trip', 'schools', 'drivers', 'tripTypes', 'students', 'selectedStudentIds'));
    }

    public function update(Request $request, TripHistory $trip): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validate($this->rules(true));
        $studentIds = array_key_exists('student_ids', $validated) ? $validated['student_ids'] : null;
        unset($validated['student_ids']);

        $trip->update($validated);

        if (is_array($studentIds)) {
            $this->syncTripStudentsForSchool($trip, $studentIds, (int) $trip->school_id);
        }

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_updated'));
    }

    public function destroy(TripHistory $trip): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $trip->delete();

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_deleted'));
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'school_id' => [$required, 'integer', 'exists:schools,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'trip_type' => ['nullable', 'string', 'max:32'],
            'bus_number' => [$required, 'string', 'max:64'],
            'route_title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'students_count' => [$required, 'integer', 'min:0'],
            'distance_km' => [$required, 'numeric', 'min:0'],
            'start_time' => [$required, 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'status' => [$required, 'in:PRESENT,ABSENT,CANCELLED,ACTIVE,COMPLETED'],
            'note' => ['nullable', 'string'],
            'student_ids' => [$partial ? 'sometimes' : 'nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }

    /**
     * @param  list<int|string>  $studentIds
     */
    private function syncTripStudentsForSchool(TripHistory $trip, array $studentIds, int $schoolId): void
    {
        $unique = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $studentIds)));

        if ($unique === []) {
            $trip->tripHistoryStudents()->delete();
            $trip->update([
                'students_count' => 0,
                'students_preview' => [],
            ]);

            return;
        }

        foreach ($unique as $sid) {
            if (! Student::query()->whereKey($sid)->where('school_id', $schoolId)->exists()) {
                throw ValidationException::withMessages([
                    'student_ids' => [__('dashboard.trip_students_school_mismatch')],
                ]);
            }
        }

        $trip->tripHistoryStudents()->delete();

        $preview = [];
        foreach ($unique as $i => $sid) {
            TripHistoryStudent::query()->create([
                'trip_history_id' => $trip->id,
                'student_id' => $sid,
                'sort_order' => $i,
                'status' => StudentTripStopStatus::IDLE->value,
            ]);
            $s = Student::query()->find($sid);
            $preview[] = [
                'id' => (string) $sid,
                'name' => (string) ($s?->full_name ?? ''),
            ];
        }

        $trip->update([
            'students_count' => count($preview),
            'students_preview' => $preview,
        ]);
    }

    private function tripHistoryVisible(TripHistory $trip): bool
    {
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }
        $id = auth()->user()?->scopingSchoolId();
        if ($id === null) {
            return false;
        }

        return (int) $trip->school_id === (int) $id;
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}

