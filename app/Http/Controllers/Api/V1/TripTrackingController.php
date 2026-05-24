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

        try {
            $data = $this->tracking->trackingPayloadForUser(
                $request->user(),
                $record,
                isset($validated['student_id']) ? (int) $validated['student_id'] : null,
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Unable to load tracking.';
            $status = str_contains($message, 'not allowed') ? 403 : 422;

            return $this->parentError($message, $e->errors(), $status);
        }

        return $this->parentSuccess($data, 'Trip tracking retrieved successfully.');
    }
}
