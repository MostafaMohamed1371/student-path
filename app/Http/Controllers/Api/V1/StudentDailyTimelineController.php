<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Student;
use App\Services\Attendance\StudentDailyTimelineService;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StudentDailyTimelineController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly StudentDailyTimelineService $timelineService,
    ) {}

    public function show(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureCanViewStudent($request, $student)) {
            return $resp;
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::parse((string) $validated['date'])->startOfDay()
            : now()->startOfDay();

        return $this->parentSuccess(
            $this->timelineService->timelineForStudent($student, $date),
        );
    }

    private function ensureCanViewStudent(Request $request, Student $student): ?JsonResponse
    {
        $driver = Driver::query()->where('user_id', $request->user()->id)->first();
        if ($driver instanceof Driver) {
            $student->loadMissing('transportRouteStudent.transportRoute');
            $routeDriverId = (int) ($student->transportRouteStudent?->transportRoute?->driver_id ?? 0);
            if ($routeDriverId === (int) $driver->id) {
                return null;
            }

            return $this->parentError('forbidden', null, 403);
        }

        if (! ParentContext::ownsStudent($request->user(), (int) $student->id)) {
            return $this->parentError('forbidden', null, 403);
        }

        return null;
    }
}
