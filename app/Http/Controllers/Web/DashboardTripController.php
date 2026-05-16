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
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\DriverTripModuleService;
use App\Services\Trips\StudentShiftFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardTripController extends Controller
{
    use ManagesDashboardScoping;

    public function __construct(
        private readonly StudentShiftFilter $studentShiftFilter,
    ) {}

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

        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $schoolId = (int) old('school_id', 0);
        $tripType = old('trip_type');
        $selectedStudentIds = array_map('intval', old('student_ids', []));
        $drivers = $schoolId > 0
            ? $this->driversForTripForm($schoolId, is_string($tripType) ? $tripType : null)
            : collect();
        $students = $schoolId > 0
            ? $this->studentsForTripForm($schoolId, is_string($tripType) ? $tripType : null, $selectedStudentIds)
            : collect();

        return view('dashboard.trips.create', [
            'schools' => $schools,
            'drivers' => $drivers,
            'tripTypes' => $tripTypes,
            'students' => $students,
            'selectedStudentIds' => $selectedStudentIds,
            'formOptionsUrl' => route('dashboard.trips.form_options'),
        ]);
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
        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $schoolId = (int) old('school_id', $trip->school_id);
        $tripType = old('trip_type', $trip->trip_type);
        $selectedStudentIds = array_map(
            'intval',
            old('student_ids', $trip->tripHistoryStudents()->pluck('student_id')->all()),
        );
        $drivers = $this->driversForTripForm($schoolId, is_string($tripType) ? $tripType : null);
        $students = $this->studentsForTripForm($schoolId, is_string($tripType) ? $tripType : null, $selectedStudentIds);

        return view('dashboard.trips.edit', [
            'trip' => $trip,
            'schools' => $schools,
            'drivers' => $drivers,
            'tripTypes' => $tripTypes,
            'students' => $students,
            'selectedStudentIds' => $selectedStudentIds,
            'formOptionsUrl' => route('dashboard.trips.form_options'),
        ]);
    }

    public function formOptions(Request $request): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['nullable', 'string', 'max:32'],
            'include_student_ids' => ['nullable', 'array'],
            'include_student_ids.*' => ['integer'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $tripType = isset($validated['trip_type']) && trim((string) $validated['trip_type']) !== ''
            ? trim((string) $validated['trip_type'])
            : null;
        $includeIds = array_map('intval', $validated['include_student_ids'] ?? []);

        return response()->json([
            'students' => $this->studentsForTripForm($schoolId, $tripType, $includeIds)
                ->map(fn (Student $s): array => $this->studentOptionRow($s))
                ->values()
                ->all(),
            'drivers' => $this->driversForTripForm($schoolId, $tripType)
                ->map(fn (Driver $d): array => $this->driverOptionRow($d))
                ->values()
                ->all(),
        ]);
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

        $tripType = is_string($trip->trip_type) && $trip->trip_type !== '' ? $trip->trip_type : null;

        foreach ($unique as $sid) {
            $student = Student::query()->whereKey($sid)->where('school_id', $schoolId)->first();
            if (! $student) {
                throw ValidationException::withMessages([
                    'student_ids' => [__('dashboard.trip_students_school_mismatch')],
                ]);
            }
            if (! $this->studentShiftFilter->studentMatchesTripType($student, $tripType)) {
                throw ValidationException::withMessages([
                    'student_ids' => [__('dashboard.trip_students_shift_mismatch')],
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

    /**
     * @param  list<int>  $includeStudentIds
     * @return Collection<int, Student>
     */
    private function studentsForTripForm(int $schoolId, ?string $tripType, array $includeStudentIds = []): Collection
    {
        if ($schoolId <= 0) {
            return collect();
        }

        $query = Student::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('full_name');

        $this->studentShiftFilter->applyToStudentQuery($query, $tripType);

        $rows = $query->get();

        if ($includeStudentIds !== []) {
            $existing = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $missing = array_values(array_diff($includeStudentIds, $existing));
            if ($missing !== []) {
                $extra = Student::query()
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $missing)
                    ->orderBy('full_name')
                    ->get();
                $rows = $rows->merge($extra)->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, Driver>
     */
    private function driversForTripForm(int $schoolId, ?string $tripType): Collection
    {
        if ($schoolId <= 0) {
            return collect();
        }

        $query = Driver::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name');

        $shift = $this->studentShiftFilter->shiftFromTripType($tripType);
        if ($shift !== null) {
            $query->where(function (Builder $q) use ($shift): void {
                $q->whereNull('shift_period')
                    ->orWhere('shift_period', $shift);
            });
        }

        return $query->get();
    }

    /**
     * @return array{id: int, label: string}
     */
    private function studentOptionRow(Student $student): array
    {
        $shift = strtoupper(trim((string) ($student->shift_period ?? '')));
        $shiftLabel = match ($shift) {
            DriverShiftResolver::MORNING => __('dashboard.student_shift_period_morning'),
            DriverShiftResolver::EVENING => __('dashboard.student_shift_period_evening'),
            StudentShiftFilter::BOTH => __('dashboard.student_shift_period_both'),
            default => __('dashboard.student_shift_period_unspecified'),
        };

        return [
            'id' => (int) $student->id,
            'label' => trim((string) $student->full_name).' — '.trim((string) $student->grade).' ('.$shiftLabel.') #'.$student->id,
        ];
    }

    /**
     * @return array{id: int, label: string}
     */
    private function driverOptionRow(Driver $driver): array
    {
        return [
            'id' => (int) $driver->id,
            'label' => trim(($driver->first_name ?? '').' '.($driver->last_name ?? '')).' (#'.(int) $driver->id.')',
        ];
    }
}

