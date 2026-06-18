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
use App\Services\Drivers\DriverServiceAreaTripFormatter;
use App\Services\TransportLines\TransportDriverCardBuilder;
use App\Services\Trips\TripRequestAcceptanceService;
use App\Services\Trips\TripRequestConflictGuard;
use App\Services\Trips\TripRequestCreator;
use App\Services\Trips\TripRequestPairingService;
use App\Services\Trips\TripRequestSlotKeyResolver;
use App\Services\Trips\TripRequestSubmissionPlanner;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;

class TripRequestController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TransportDriverCardBuilder $transportDriverCardBuilder,
        private readonly TripRequestAcceptanceService $tripRequestAcceptanceService,
        private readonly TripRequestConflictGuard $tripRequestConflictGuard,
        private readonly TripRequestCreator $tripRequestCreator,
        private readonly TripRequestSubmissionPlanner $submissionPlanner,
        private readonly TripRequestSlotKeyResolver $slotKeyResolver,
        private readonly TripRequestPairingService $tripRequestPairingService,
        private readonly DriverServiceAreaTripFormatter $driverServiceAreaTripFormatter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if ($driver) {
            $this->tripRequestConflictGuard->closeStalePendingRequestsForDriver((int) $driver->id);
        }

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
        $routes = $this->transportDriverCardBuilder->latestTripRouteMetaForDrivers($schoolIds, $drivers);
        [$queryLat, $queryLng] = $this->queryCoordinates($request);
        $addressInformationByDriver = $this->driverServiceAreaTripFormatter->addressInformationByDriverIds(
            $collection->pluck('driver_id')->map(fn ($id): int => (int) $id)->all(),
        );

        return $this->parentSuccess([
            'items' => $collection
                ->map(fn (TripRequest $tr): array => $this->tripRequestPayload(
                    $tr,
                    $request,
                    $queryLat,
                    $queryLng,
                    $reserved,
                    $routes,
                    $addressInformationByDriver,
                ))
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

        $student = Student::query()->with('guardian')->findOrFail((int) $validated['student_id']);
        $trip = ! empty($validated['trip_history_id'])
            ? TripHistory::query()->find((int) $validated['trip_history_id'])
            : null;

        try {
            $plan = $this->submissionPlanner->plan(
                $request->user(),
                $student,
                $trip,
                ! empty($validated['driver_id']) ? (int) $validated['driver_id'] : null,
                $validated['present_type'] ?? null,
                [
                    'present_type' => $validated['present_type'] ?? null,
                    'moving_point' => $validated['moving_point'] ?? null,
                    'stop_point' => $validated['stop_point'] ?? null,
                    'subscribe_price' => isset($validated['subscribe_price']) ? (float) $validated['subscribe_price'] : null,
                ],
            );
        } catch (ValidationException $e) {
            return $this->parentError(
                collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                $e->errors(),
                422,
            );
        }

        [$row, $created] = $this->tripRequestCreator->createOrReturnExistingPending(
            $request->user(),
            $student,
            $plan->driverId,
            [
                'trip_history_id' => $plan->tripHistoryId,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                ...$plan->snapshot,
            ],
        );

        $pairedRow = null;
        $pairedCreated = false;
        if ($created) {
            [$pairedRow, $pairedCreated] = $this->tripRequestPairingService->createPendingReturnCompanion(
                $request->user(),
                $student,
                $row,
            );
        }

        $loaded = $row->fresh()->load(['student.school', 'driver.user', 'driver.bus', 'tripHistory']);
        [$queryLat, $queryLng] = $this->queryCoordinates($request, $validated);

        $payload = $this->tripRequestPayload($loaded, $request, $queryLat, $queryLng);
        if ($pairedRow instanceof TripRequest) {
            $payload['paired_trip_request'] = [
                'id' => (int) $pairedRow->id,
                'trip_slot' => $this->slotKeyResolver->slotKeyForRequest($pairedRow->fresh(['tripHistory'])),
                'created' => $pairedCreated,
            ];
        }

        return $this->parentSuccess(
            $payload,
            $created ? 'Trip request created' : 'Trip request already pending',
            $created ? 201 : 200,
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

        $this->tripRequestPairingService->handleParentCancellation($trip_request->fresh(['tripHistory']));

        return $this->parentSuccess($trip_request->fresh(), 'Trip request cancelled');
    }

    public function update(Request $request, TripRequest $trip_request): JsonResponse
    {
        if (! $this->canAccessTripRequest($request, $trip_request)) {
            return $this->parentError('forbidden', null, 403);
        }

        $driver = $this->currentDriver($request);
        if ($driver && (int) $trip_request->driver_id === (int) $driver->id) {
            $validated = $request->validate([
                'status' => ['required', 'string', 'in:accepted,rejected'],
            ]);

            if ($trip_request->status !== 'pending') {
                if ($validated['status'] === 'accepted'
                    && $this->tripRequestConflictGuard->slotTakenByAnotherDriver($trip_request)) {
                    return $this->parentError(
                        __('dashboard.trip_request_slot_taken_by_another_driver'),
                        ['status' => [__('dashboard.trip_request_slot_taken_by_another_driver')]],
                        422,
                        [
                            'id' => (int) $trip_request->id,
                            'status' => $trip_request->fresh()->status,
                        ],
                    );
                }

                return $this->parentError('Only pending trip requests can be updated.', null, 422);
            }

            try {
                $this->tripRequestAcceptanceService->applyDriverDecision($trip_request, $validated['status']);
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first()
                    ?: __('dashboard.trip_request_only_pending_status');

                $trip_request->refresh();

                return $this->parentError(
                    $message,
                    $e->errors(),
                    422,
                    $this->tripRequestConflictGuard->slotTakenByAnotherDriver($trip_request)
                        ? ['id' => (int) $trip_request->id, 'status' => $trip_request->status]
                        : null,
                );
            }

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
     * @param  array<int, list<array<string, mixed>>>|null  $addressInformationByDriver
     * @return array<string, mixed>
     */
    private function tripRequestPayload(
        TripRequest $tripRequest,
        Request $request,
        ?float $queryLat = null,
        ?float $queryLng = null,
        ?Collection $reservedByDriver = null,
        ?array $routeBySchoolAndBus = null,
        ?array $addressInformationByDriver = null,
    ): array {
        $tripRequest->loadMissing(['student.school', 'driver.user', 'driver.bus', 'tripHistory']);

        $driverCard = null;
        $parentUser = User::query()->find((int) $tripRequest->user_id);
        $pickupNeighborhoodId = $this->transportDriverCardBuilder->resolvePickupNeighborhoodId(
            $queryLat,
            $queryLng,
            $tripRequest->student,
            $parentUser ?? $request->user(),
        );

        if ($tripRequest->driver) {
            $driver = $tripRequest->driver;
            $reserved = $reservedByDriver ?? $this->transportDriverCardBuilder->reservedCountsByDriverId(collect([$driver]));
            $routes = $routeBySchoolAndBus ?? $this->transportDriverCardBuilder->latestTripRouteMetaForDrivers(
                [(int) $driver->school_id],
                collect([$driver]),
            );
            $school = $tripRequest->student?->school;
            if (! $school instanceof School || (int) $school->id !== (int) $driver->school_id) {
                $school = School::query()->find((int) $driver->school_id);
            }
            $distanceKm = $this->transportDriverCardBuilder->resolveDistanceKmToSchool(
                $queryLat,
                $queryLng,
                $tripRequest->student,
                $parentUser ?? $request->user(),
                $school,
            );
            $driverCard = $this->transportDriverCardBuilder->buildCard(
                $driver,
                $reserved,
                $routes,
                $distanceKm,
                null,
                $tripRequest->student,
                null,
                $pickupNeighborhoodId,
            );
        }

        $tripPreview = $tripRequest->student
            ? $this->tripPreviewForTripRequest($tripRequest)
            : [
                'pickupLabel' => 'Unknown pickup',
                'destinationLabel' => 'Unknown destination',
            ];

        $driverId = (int) ($tripRequest->driver_id ?? 0);
        $addressInformation = $driverId > 0
            ? ($addressInformationByDriver[$driverId] ?? $this->driverServiceAreaTripFormatter->serviceAreasForDriver($driverId))
            : [];
        $addressInformation = $this->driverServiceAreaTripFormatter->filterAddressInformationForPickupNeighborhood(
            $addressInformation,
            $pickupNeighborhoodId,
        );

        return array_merge($tripRequest->toArray(), [
            'trip_slot' => $this->slotKeyResolver->slotKeyForRequest($tripRequest),
            'parentName' => $tripRequest->parentDisplayName(),
            'parentPhone' => $tripRequest->parentDisplayPhone(),
            'driverName' => $tripRequest->driver ? $tripRequest->driverDisplayName() : null,
            'driverCard' => $driverCard,
            'tripPreview' => $tripPreview,
            'address_information' => $addressInformation,
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
