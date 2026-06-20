<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitTripDriverRatingRequest;
use App\Models\Bus;
use App\Models\Student;
use App\Models\TripHistory;
use App\Services\Trips\DriverTripRatingService;
use App\Services\Trips\StudentTripStatusResolver;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TripParentController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly StudentTripStatusResolver $tripStatusResolver,
        private readonly DriverTripRatingService $driverTripRatingService,
    ) {}

    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = TripHistory::query()
            ->where('status', '!=', 'CANCELLED')
            ->where('start_time', '>=', now()->startOfDay());

        if ($request->filled('student_id')) {
            $student = Student::query()->findOrFail((int) $request->query('student_id'));
            if (! $this->isApiAdmin($request->user()) && ! ParentContext::ownsStudent($request->user(), (int) $student->id)) {
                return $this->parentError('forbidden', null, 403);
            }
            if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
                return $resp;
            }
            $query->where('school_id', $student->school_id);
        } else {
            $schoolIds = ParentContext::studentsFor($request->user())->pluck('school_id')->unique()->filter()->values()->all();
            if ($schoolIds !== []) {
                $query->whereIn('school_id', $schoolIds);
            } else {
                $this->applyApiScopeBySchoolIdColumn($query, $request->user());
            }
        }

        $paginator = $query
            ->orderBy('start_time')
            ->paginate(
                min(100, max(1, (int) $request->query('per_page', 20))),
                ['*'],
                'page',
                max(1, (int) $request->query('page', 1))
            );

        return $this->parentSuccess(
            collect($paginator->items())->map(fn (TripHistory $t) => $this->tripSummary($t))->values()->all(),
            'success',
            200,
            [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]
        );
    }

    public function active(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        if ($request->filled('student_id')) {
            $student = Student::query()->findOrFail((int) $request->query('student_id'));
            if (! $this->isApiAdmin($request->user()) && ! ParentContext::ownsStudent($request->user(), (int) $student->id)) {
                return $this->parentError('forbidden', null, 403);
            }
            if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
                return $resp;
            }
            $trip = $this->tripStatusResolver->currentTripForStudent($student);

            return $this->parentSuccess([
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'trip' => $this->tripStatusResolver->tripPayload($trip),
            ]);
        }

        $students = ParentContext::studentsFor($request->user());
        $out = [];

        foreach ($students as $student) {
            $trip = $this->tripStatusResolver->currentTripForStudent($student);
            if ($trip) {
                $out[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'trip' => $this->tripStatusResolver->tripPayload($trip),
                ];
            }
        }

        return $this->parentSuccess(['active' => $out]);
    }

    public function show(Request $request, TripHistory $trip): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), $trip->school_id)) {
            return $resp;
        }

        return $this->parentSuccess($this->tripDetail($trip));
    }

    public function rateDriver(SubmitTripDriverRatingRequest $request, TripHistory $trip): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), $trip->school_id)) {
            return $resp;
        }

        try {
            $result = $this->driverTripRatingService->submitForParent(
                $request->user(),
                $trip,
                (int) $request->validated('rating'),
                $request->validated('comment'),
                $request->filled('student_id') ? (int) $request->validated('student_id') : null,
            );
        } catch (ValidationException $e) {
            return $this->parentError(
                collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                $e->errors(),
                422,
            );
        }

        $record = $result['rating'];

        return $this->parentSuccess([
            'rating_id' => (int) $record->id,
            'trip_id' => (int) $trip->id,
            'driver_id' => (int) $record->driver_id,
            'student_id' => $record->student_id !== null ? (int) $record->student_id : null,
            'rating' => (int) $record->rating,
            'comment' => $record->comment,
            'updated' => (bool) $result['updated'],
            'driver_rating_avg' => $result['driver_rating_avg'],
            'driver_rating_count' => $result['driver_rating_count'],
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ], $result['updated'] ? 'Driver rating updated successfully' : 'Driver rated successfully');
    }

    public function driver(Request $request, TripHistory $trip): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), $trip->school_id)) {
            return $resp;
        }

        $bus = null;
        if ($trip->school_id && $trip->bus_number) {
            $bus = Bus::query()
                ->where('number', $trip->bus_number)
                ->whereHas('driver', fn ($q) => $q->where('school_id', $trip->school_id))
                ->with('driver')
                ->first();
        }

        if (! $bus || ! $bus->driver) {
            return $this->parentSuccess(null, 'No driver linked for this trip');
        }

        $driver = $bus->driver;

        return $this->parentSuccess([
            'driver' => [
                'id' => $driver->id,
                'first_name' => $driver->first_name,
                'father_name' => $driver->father_name,
                'grandfather_name' => $driver->grandfather_name,
                'last_name' => $driver->last_name,
                'primary_phone' => $driver->primary_phone,
                'emergency_phone' => $driver->emergency_phone,
                'license_number' => $driver->license_number,
            ],
            'bus' => [
                'id' => $bus->id,
                'name' => $bus->name,
                'number' => $bus->number,
                'type' => $bus->type,
                'capacity' => $bus->capacity,
                'color' => $bus->color,
            ],
        ]);
    }

    private function tripDetail(TripHistory $trip): array
    {
        $row = $this->tripSummary($trip);
        $preview = is_array($trip->students_preview) ? $trip->students_preview : [];
        $row['students_preview'] = array_values(array_filter($preview, 'is_array'));
        $row['distance_km'] = (float) $trip->distance_km;
        $row['note'] = $trip->note;

        return $row;
    }

    private function tripSummary(TripHistory $trip): array
    {
        $start = $trip->start_time instanceof Carbon ? $trip->start_time : Carbon::parse((string) $trip->start_time);
        $end = $trip->end_time ? ($trip->end_time instanceof Carbon ? $trip->end_time : Carbon::parse((string) $trip->end_time)) : null;

        return [
            'id' => $trip->id,
            'school_id' => $trip->school_id,
            'bus_number' => (string) ($trip->bus_number ?? ''),
            'route_title' => (string) ($trip->route_title ?? ''),
            'location' => (string) ($trip->location ?? ''),
            'status' => (string) ($trip->status ?? ''),
            'start_time' => $start->toIso8601String(),
            'end_time' => $end?->toIso8601String(),
            'students_count' => (int) $trip->students_count,
        ];
    }
}
