<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    use FormatsParentApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Absence::query()->where('user_id', $request->user()->id)->with('student');

        if ($request->filled('student_id')) {
            $sid = (int) $request->query('student_id');
            if (! ParentContext::ownsStudent($request->user(), $sid)) {
                return $this->parentError('forbidden', null, 403);
            }
            $query->where('student_id', $sid);
        }

        if ($request->filled('from')) {
            $query->whereDate('end_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('start_date', '<=', (string) $request->query('to'));
        }

        $rows = $query->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return $this->parentSuccess([
            'items' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! ParentContext::ownsStudent($request->user(), (int) $validated['student_id'])) {
            return $this->parentError('forbidden', null, 403);
        }

        $row = Absence::query()->create([
            'user_id' => $request->user()->id,
            'student_id' => $validated['student_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->parentSuccess($row->load('student'), 'Absence reported', 201);
    }

    public function show(Request $request, Absence $absence): JsonResponse
    {
        if ((int) $absence->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        return $this->parentSuccess($absence->load('student'));
    }

    public function update(Request $request, Absence $absence): JsonResponse
    {
        if ((int) $absence->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'string', 'max:255'],
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

        return $this->parentSuccess($absence->fresh()->load('student'), 'Absence updated');
    }

    public function destroy(Request $request, Absence $absence): JsonResponse
    {
        if ((int) $absence->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $absence->delete();

        return $this->parentSuccess((object) [], 'Absence deleted');
    }
}
