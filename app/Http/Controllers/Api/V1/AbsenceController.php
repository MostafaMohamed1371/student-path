<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AbsenceResource;
use App\Models\Absence;
use App\Models\Driver;
use App\Services\Absences\AbsenceReporter;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\AbsenceReason;

class AbsenceController extends Controller
{
    use FormatsParentApiResponse;

    public function index(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        $query = Absence::query()->with(['student', 'driver']);

        if ($driver instanceof Driver) {
            $query->where('driver_id', $driver->id);
        } else {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('student_id')) {
            $sid = (int) $request->query('student_id');
            if (! $driver && ! ParentContext::ownsStudent($request->user(), $sid)) {
                return $this->parentError('forbidden', null, 403);
            }
            $query->where('student_id', $sid);
        }

        if ($request->filled('date')) {
            $day = (string) $request->query('date');
            $query->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day);
        }

        if ($request->filled('from')) {
            $query->whereDate('end_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('start_date', '<=', (string) $request->query('to'));
        }

        $rows = $query->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return $this->parentSuccess([
            'items' => AbsenceResource::collection(collect($rows->items()))->toArray($request),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, AbsenceReporter $reporter): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', Rule::in(array_column(AbsenceReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! ParentContext::ownsStudent($request->user(), (int) $validated['student_id'])) {
            return $this->parentError('forbidden', null, 403);
        }

        $row = $reporter->reportForParent($request->user(), $validated);

        return $this->parentSuccess(
            (new AbsenceResource($row))->toArray($request),
            'Absence reported',
            201,
        );
    }

    public function show(Request $request, Absence $absence): JsonResponse
    {
        if (! $this->canViewAbsence($request, $absence)) {
            return $this->parentError('forbidden', null, 403);
        }

        $absence->load(['student', 'driver']);

        return $this->parentSuccess((new AbsenceResource($absence))->toArray($request));
    }

    public function update(Request $request, Absence $absence): JsonResponse
    {
        if ((int) $absence->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'string', Rule::in(array_column(AbsenceReason::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $start = isset($validated['start_date'])
            ? (string) $validated['start_date']
            : $absence->start_date->toDateString();
        $end = isset($validated['end_date'])
            ? (string) $validated['end_date']
            : $absence->end_date->toDateString();
        if ($end < $start) {
            return $this->parentError('end_date must be on or after start_date.', ['end_date' => ['Invalid range']], 422);
        }

        $absence->fill($validated)->save();

        return $this->parentSuccess(
            (new AbsenceResource($absence->fresh()->load(['student', 'driver'])))->toArray($request),
            'Absence updated',
        );
    }

    public function destroy(Request $request, Absence $absence): JsonResponse
    {
        if ((int) $absence->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $absence->delete();

        return $this->parentSuccess((object) [], 'Absence deleted');
    }

    private function canViewAbsence(Request $request, Absence $absence): bool
    {
        $driver = $this->currentDriver($request);
        if ($driver && (int) $absence->driver_id === (int) $driver->id) {
            return true;
        }

        return (int) $absence->user_id === (int) $request->user()->id;
    }

    private function currentDriver(Request $request): ?Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->first();
    }
}
