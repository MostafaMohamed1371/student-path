<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Student;
use App\Services\Attendance\StudentAttendanceScheduleService;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAttendanceController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly StudentAttendanceScheduleService $scheduleService,
    ) {}

    public function schedule(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureCanViewStudent($request, $student)) {
            return $resp;
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'recent_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $now = now();
        $year = (int) ($validated['year'] ?? $now->year);
        $month = (int) ($validated['month'] ?? $now->month);
        $recentLimit = (int) ($validated['recent_limit'] ?? 10);

        $payload = $this->scheduleService->scheduleForStudent($student, $year, $month, $recentLimit);

        return $this->parentSuccess($payload);
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
