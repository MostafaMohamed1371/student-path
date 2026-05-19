<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\TransportLines\TransportDriverCardBuilder;
use App\Services\Trips\TripRequestAcceptanceService;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\TripRequestOrderSnapshot;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TripRequestController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TransportDriverCardBuilder $transportDriverCardBuilder,
        private readonly TripRequestAcceptanceService $tripRequestAcceptanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        $query = TripRequest::query()->with(['student', 'driver', 'tripHistory'])->latest('id');
        if ($driver) {
            $query->where('driver_id', $driver->id);
        } else {
            $query->where('user_id', $request->user()->id);
        }
        $rows = $query->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $collection = collect($rows->items());
        $collection->each(fn (TripRequest $tr) => $tr->loadMissing(['student.school', 'driver.user', 'driver.bus', 'tripHistory']));

        $drivers = $collection->pluck('driver')->filter();
        $reserved = $this->transportDriverCardBuilder->reservedCountsByDriverId($drivers);
        $schoolIds = $collection
            ->map(fn (TripRequest $tr): int => (int) ($tr->student?->school_id ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $routes = $this->transportDriverCardBuilder->latestRouteTitlesBySchoolAndBus($schoolIds, $drivers);
        [$queryLat, $queryLng] = $this->queryCoordinates($request);

        return $this->parentSuccess([
            'items' => $collection
                ->map(fn (TripRequest $tr): array => $this->tripRequestPayload($tr, $request, $queryLat, $queryLng, $reserved, $routes))
                ->values()
                ->all(),
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
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'trip_history_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'present_type' => ['nullable', 'string', 'max:64'],
            'moving_point' => ['nullable', 'string', 'max:2000'],
            'stop_point' => ['nullable', 'string', 'max:2000'],
            'subscribe_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! ParentContext::ownsStudent($request->user(), (int) $validated['student_id'])) {
            return $this->parentError('forbidden', null, 403);
        }

        if (! empty($validated['trip_history_id'])) {
            $trip = TripHistory::query()->find((int) $validated['trip_history_id']);
            $allowedSchools = ParentContext::studentsFor($request->user())->pluck('school_id')->unique()->filter();
            if ($trip && $allowedSchools->isNotEmpty() && ! $allowedSchools->contains($trip->school_id)) {
                return $this->parentError('Trip is not in scope for your students.', ['trip' => ['Out of scope']], 422);
            }
        }

        $student = Student::query()->with('guardian')->findOrFail((int) $validated['student_id']);
        ParentContext::ensureUserLinkedToStudent($request->user(), $student);
        $targetShift = app(DriverShiftResolver::class)->fromPresentType($validated['present_type'] ?? null);
        if ($targetShift === null && ! empty($validated['trip_history_id'])) {
            $selectedTrip = TripHistory::query()->find((int) $validated['trip_history_id']);
            $targetShift = app(DriverShiftResolver::class)->fromTripType($selectedTrip?->trip_type);
        }

        $driverId = null;
        if (! empty($validated['driver_id'])) {
            $chosen = Driver::query()->findOrFail((int) $validated['driver_id']);
            if ($chosen->status !== 'active') {
                return $this->parentError('Selected driver is not available.', ['driver_id' => ['inactive']], 422);
            }
            if ((int) $chosen->school_id !== (int) $student->school_id) {
                return $this->parentError(
                    'Driver does not belong to this student\'s school.',
                    ['driver_id' => ['school_mismatch']],
                    422
                );
            }
            if ($targetShift !== null
                && $chosen->shift_period !== null
                && $chosen->shift_period !== 'BOTH'
                && $chosen->shift_period !== $targetShift) {
                return $this->parentError(
                    'Selected driver shift does not match the requested trip period.',
                    ['driver_id' => ['shift_mismatch']],
                    422
                );
            }
            $driverId = $chosen->id;
        } else {
            $driverQuery = Driver::query()
                ->where('school_id', $student->school_id)
                ->where('status', 'active')
                ->orderBy('id');

            if ($targetShift !== null) {
                $driverId = (clone $driverQuery)
                    ->where(function ($q) use ($targetShift): void {
                        $q->where('shift_period', $targetShift)->orWhere('shift_period', 'BOTH');
                    })
                    ->value('id');
                if ($driverId === null) {
                    $driverId = $driverQuery->value('id');
                }
            } else {
                $driverId = $driverQuery->value('id');
            }
        }

        $assignedDriver = $driverId !== null ? Driver::query()->find((int) $driverId) : null;
        $snapshot = TripRequestOrderSnapshot::build($student, $assignedDriver, [
            'present_type' => $validated['present_type'] ?? null,
            'moving_point' => $validated['moving_point'] ?? null,
            'stop_point' => $validated['stop_point'] ?? null,
            'subscribe_price' => isset($validated['subscribe_price']) ? (float) $validated['subscribe_price'] : null,
        ]);

        $row = TripRequest::query()->create([
            'user_id' => $request->user()->id,
            'student_id' => $validated['student_id'],
            'driver_id' => $driverId,
            'trip_history_id' => $validated['trip_history_id'] ?? null,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            ...$snapshot,
        ]);

        $loaded = $row->fresh()->load(['student.school', 'driver.user', 'driver.bus', 'tripHistory']);
        [$queryLat, $queryLng] = $this->queryCoordinates($request, $validated);

        return $this->parentSuccess(
            $this->tripRequestPayload($loaded, $request, $queryLat, $queryLng),
            'Trip request created',
            201,
        );
    }

    public function show(Request $request, TripRequest $trip_request): JsonResponse
    {
        if (! $this->canAccessTripRequest($request, $trip_request)) {
            return $this->parentError('forbidden', null, 403);
        }

        $trip_request->loadMissing(['student.school', 'driver.user', 'driver.bus', 'tripHistory']);
        [$queryLat, $queryLng] = $this->queryCoordinates($request);

        return $this->parentSuccess(
            $this->tripRequestPayload($trip_request, $request, $queryLat, $queryLng),
        );
    }

    public function cancel(Request $request, TripRequest $trip_request): JsonResponse
    {
        if ((int) $trip_request->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        if ($trip_request->status === 'cancelled') {
            return $this->parentSuccess($trip_request, 'Already cancelled');
        }

        if ($trip_request->status !== 'pending') {
            return $this->parentError('Only pending trip requests can be cancelled.', null, 422);
        }

        $trip_request->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $this->parentSuccess($trip_request->fresh(), 'Trip request cancelled');
    }

    public function update(Request $request, TripRequest $trip_request): JsonResponse
    {
        if (! $this->canAccessTripRequest($request, $trip_request)) {
            return $this->parentError('forbidden', null, 403);
        }

        $driver = $this->currentDriver($request);
        if ($driver && (int) $trip_request->driver_id === (int) $driver->id) {
            if ($trip_request->status !== 'pending') {
                return $this->parentError('Only pending trip requests can be updated.', null, 422);
            }

            $validated = $request->validate([
                'status' => ['required', 'string', 'in:accepted,rejected'],
            ]);

            $this->tripRequestAcceptanceService->applyDriverDecision($trip_request, $validated['status']);

            return $this->parentSuccess(
                $trip_request->fresh()->load(['student', 'driver', 'tripHistory']),
                'Trip request status updated'
            );
        }

        if ($trip_request->status !== 'pending') {
            return $this->parentError('Only pending trip requests can be updated.', null, 422);
        }

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'trip_history_id' => ['sometimes', 'nullable', 'integer', 'exists:trip_histories,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['student_id'])
            && ! ParentContext::ownsStudent($request->user(), (int) $validated['student_id'])) {
            return $this->parentError('forbidden', null, 403);
        }

        $newTripHistoryId = array_key_exists('trip_history_id', $validated)
            ? $validated['trip_history_id']
            : $trip_request->trip_history_id;

        if ($newTripHistoryId !== null) {
            $trip = TripHistory::query()->find((int) $newTripHistoryId);
            $allowedSchools = ParentContext::studentsFor($request->user())->pluck('school_id')->unique()->filter();
            if ($trip && $allowedSchools->isNotEmpty() && ! $allowedSchools->contains($trip->school_id)) {
                return $this->parentError('Trip is not in scope for your students.', ['trip' => ['Out of scope']], 422);
            }
        }

        $trip_request->fill($validated)->save();

        return $this->parentSuccess($trip_request->fresh()->load(['student', 'driver', 'tripHistory']), 'Trip request updated');
    }

    public function destroy(Request $request, TripRequest $trip_request): JsonResponse
    {
        if ((int) $trip_request->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        if ($trip_request->status !== 'pending') {
            return $this->parentError('Only pending trip requests can be deleted.', null, 422);
        }

        $trip_request->delete();

        return $this->parentSuccess((object) [], 'Trip request deleted');
    }

    /**
     * Same shape as POST /api/trip-requests {@see store} response body.
     *
     * @param  Collection<int|string, int>|null  $reservedByDriver
     * @param  array<string, string>|null  $routeBySchoolAndBus
     * @return array<string, mixed>
     */
    private function tripRequestPayload(
        TripRequest $tripRequest,
        Request $request,
        ?float $queryLat = null,
        ?float $queryLng = null,
        ?Collection $reservedByDriver = null,
        ?array $routeBySchoolAndBus = null,
    ): array {
        $tripRequest->loadMissing(['student.school', 'driver.user', 'driver.bus', 'tripHistory']);

        $driverCard = null;
        if ($tripRequest->driver) {
            $driver = $tripRequest->driver;
            $reserved = $reservedByDriver ?? $this->transportDriverCardBuilder->reservedCountsByDriverId(collect([$driver]));
            $routes = $routeBySchoolAndBus ?? $this->transportDriverCardBuilder->latestRouteTitlesBySchoolAndBus(
                [(int) $driver->school_id],
                collect([$driver]),
            );
            $school = $tripRequest->student?->school;
            if (! $school instanceof School || (int) $school->id !== (int) $driver->school_id) {
                $school = School::query()->find((int) $driver->school_id);
            }
            $parentUser = User::query()->find((int) $tripRequest->user_id);
            $distanceKm = $this->transportDriverCardBuilder->resolveDistanceKmToSchool(
                $queryLat,
                $queryLng,
                $tripRequest->student,
                $parentUser ?? $request->user(),
                $school,
            );
            $driverCard = $this->transportDriverCardBuilder->buildCard($driver, $reserved, $routes, $distanceKm);
        }

        $tripPreview = $tripRequest->student
            ? $this->tripPreviewForTripRequest($tripRequest)
            : [
                'pickupLabel' => 'Unknown pickup',
                'destinationLabel' => 'Unknown destination',
            ];

        return array_merge($tripRequest->toArray(), [
            'parentName' => $tripRequest->parentDisplayName(),
            'parentPhone' => $tripRequest->parentDisplayPhone(),
            'driverName' => $tripRequest->driver ? $tripRequest->driverDisplayName() : null,
            'driverCard' => $driverCard,
            'tripPreview' => $tripPreview,
        ]);
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function queryCoordinates(Request $request, ?array $validated = null): array
    {
        $validated ??= $request->all();
        $lat = array_key_exists('latitude', $validated) && $validated['latitude'] !== null
            ? (float) $validated['latitude']
            : ($request->filled('latitude') ? (float) $request->query('latitude') : null);
        $lng = array_key_exists('longitude', $validated) && $validated['longitude'] !== null
            ? (float) $validated['longitude']
            : ($request->filled('longitude') ? (float) $request->query('longitude') : null);

        return [$lat, $lng];
    }

    /**
     * @return array{pickupLabel: string, destinationLabel: string}
     */
    private function tripPreviewForTripRequest(TripRequest $tripRequest): array
    {
        $student = $tripRequest->student;
        if (! $student instanceof Student) {
            return [
                'pickupLabel' => 'Unknown pickup',
                'destinationLabel' => 'Unknown destination',
            ];
        }

        $student->loadMissing('school');
        $parent = User::query()->with('homeLocation')->find((int) $tripRequest->user_id);
        $home = $parent?->homeLocation;
        $school = $student->school;

        $pickup = $home?->formatted_address
            ?? (isset($home?->latitude, $home?->longitude) ? ($home->latitude.', '.$home->longitude) : null)
            ?? 'Unknown pickup';

        $destination = $school?->name_ar
            ?? $school?->name_en
            ?? $school?->address
            ?? (isset($school?->latitude, $school?->longitude) ? ($school->latitude.', '.$school->longitude) : null)
            ?? 'Unknown destination';

        return [
            'pickupLabel' => $pickup,
            'destinationLabel' => $destination,
        ];
    }

    private function currentDriver(Request $request): ?Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->first();
    }

    private function canAccessTripRequest(Request $request, TripRequest $tripRequest): bool
    {
        if ((int) $tripRequest->user_id === (int) $request->user()->id) {
            return true;
        }

        $driver = $this->currentDriver($request);

        return $driver && (int) $tripRequest->driver_id === (int) $driver->id;
    }
}
