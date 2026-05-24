<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TripHistory;
use App\Services\Trips\DriverTripModuleService;
use App\Services\Trips\TripLocationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DriverTripLocationController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TripLocationTrackingService $tracking,
        private readonly DriverTripModuleService $tripIds,
    ) {}

    public function store(Request $request, string $trip): JsonResponse
    {
        $driver = Driver::query()->where('user_id', $request->user()->id)->first();
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can send trip location.', null, 403);
        }

        $tripPk = $this->tripIds->parseTripPublicId($trip);
        if ($tripPk === null) {
            return $this->parentError('Invalid trip id.', null, 422);
        }

        $record = TripHistory::query()->find($tripPk);
        if (! $record) {
            return $this->parentError('Trip not found.', null, 404);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        try {
            $data = $this->tracking->updateDriverLocation($driver, $record, [
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'heading' => isset($validated['heading']) ? (float) $validated['heading'] : null,
                'speed_kmh' => isset($validated['speed_kmh']) ? (float) $validated['speed_kmh'] : null,
                'accuracy_m' => isset($validated['accuracy_m']) ? (float) $validated['accuracy_m'] : null,
                'recorded_at' => $validated['recorded_at'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return $this->parentError(
                collect($e->errors())->flatten()->first() ?: 'Validation error.',
                $e->errors(),
                422,
            );
        }

        return $this->parentSuccess($data, 'Location updated successfully.', 201);
    }
}
