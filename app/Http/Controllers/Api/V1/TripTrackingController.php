<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TripHistory;
use App\Services\Trips\DriverTripModuleService;
use App\Services\Trips\TripLocationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TripTrackingController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TripLocationTrackingService $tracking,
        private readonly DriverTripModuleService $tripIds,
    ) {}

    public function show(Request $request, string $trip): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $record = $this->resolveTripForTracking($request, $trip);
        if ($record instanceof JsonResponse) {
            return $record;
        }

        try {
            $data = $this->tracking->trackingPayloadForUser(
                $request->user(),
                $record,
                isset($validated['student_id']) ? (int) $validated['student_id'] : null,
            );
        } catch (ValidationException $e) {
            return $this->trackingErrorResponse($e);
        }

        return $this->parentSuccess($data, 'Trip tracking retrieved successfully.');
    }

    /**
     * Lightweight snapshot matching Firebase path trips/{id}/tracking/location.
     */
    public function location(Request $request, string $trip): JsonResponse
    {
        $record = $this->resolveTripForTracking($request, $trip);
        if ($record instanceof JsonResponse) {
            return $record;
        }

        try {
            $data = $this->tracking->trackingPayloadForUser($request->user(), $record);
        } catch (ValidationException $e) {
            return $this->trackingErrorResponse($e);
        }

        $location = is_array($data['location'] ?? null) ? $data['location'] : null;

        return $this->parentSuccess([
            'trip_id' => $data['trip_id'] ?? null,
            'trip_history_id' => $data['trip_history_id'] ?? null,
            'tracking_active' => (bool) ($data['tracking_active'] ?? false),
            'location' => $location,
            'firebase_path' => $data['realtime']['firebase_path'] ?? null,
        ], 'Trip location retrieved successfully.');
    }

    private function resolveTripForTracking(Request $request, string $trip): TripHistory|JsonResponse
    {
        $tripPk = $this->tripIds->parseTripPublicId($trip);
        if ($tripPk === null) {
            return $this->parentError('Invalid trip id.', null, 422);
        }

        $record = TripHistory::query()->find($tripPk);
        if (! $record) {
            return $this->parentError('Trip not found.', null, 404);
        }

        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $record->school_id)) {
            return $resp;
        }

        return $record;
    }

    private function trackingErrorResponse(ValidationException $e): JsonResponse
    {
        $message = collect($e->errors())->flatten()->first() ?: 'Unable to load tracking.';
        $status = str_contains($message, 'not allowed') ? 403 : 422;

        return $this->parentError($message, $e->errors(), $status);
    }
}
