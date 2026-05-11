<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBusRequest;
use App\Http\Requests\Api\UpdateBusRequest;
use App\Http\Resources\BusResource;
use App\Models\Bus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusController extends Controller
{
    public function showMyBus(Request $request): JsonResponse
    {
        $driverId = $request->user()?->driver?->id;
        if (! $driverId) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'driver not found for this account',
            ], 422);
        }

        $bus = Bus::query()->where('driver_id', $driverId)->first();

        if (! $bus) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'bus not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => (new BusResource($bus))->toArray($request),
            'msg' => 'success',
        ]);
    }

    public function store(StoreBusRequest $request): JsonResponse
    {
        $driverId = $request->user()?->driver?->id;
        if (! $driverId) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'driver not found for this account',
            ], 422);
        }

        if (Bus::query()->where('driver_id', $driverId)->exists()) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'driver already has a bus',
            ], 422);
        }

        $validated = $request->validated();

        $bus = Bus::query()->create([
            'user_id' => $request->user()->id,
            'driver_id' => $driverId,
            'name' => $validated['busName'],
            'type' => $validated['busType'],
            'vehicle_model_year' => $validated['busModelYear'] ?? null,
            'ac_status' => $validated['busAcStatus'] ?? null,
            'city' => $validated['busCity'],
            'number' => $validated['busNumber'],
            'color' => $validated['busColor'],
            'capacity' => $validated['busCapacity'],
            'fuel_type' => $validated['fuelType'],
            'status' => $validated['busStatus'],
            'annual_status' => $validated['busAnnualStatus'],
            'insurance' => $validated['busInsurance'],
        ]);

        return response()->json([
            'success' => true,
            'data' => (new BusResource($bus))->toArray($request),
            'msg' => 'bus created successfully',
        ], 201);
    }

    public function update(UpdateBusRequest $request): JsonResponse
    {
        $driverId = $request->user()?->driver?->id;
        if (! $driverId) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'driver not found for this account',
            ], 422);
        }

        $bus = Bus::query()->where('driver_id', $driverId)->first();

        if (! $bus) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'bus not found',
            ], 404);
        }

        $validated = $request->validated();

        $bus->fill([
            'name' => $validated['busName'] ?? $bus->name,
            'type' => $validated['busType'] ?? $bus->type,
            'vehicle_model_year' => array_key_exists('busModelYear', $validated)
                ? $validated['busModelYear']
                : $bus->vehicle_model_year,
            'ac_status' => array_key_exists('busAcStatus', $validated)
                ? $validated['busAcStatus']
                : $bus->ac_status,
            'city' => $validated['busCity'] ?? $bus->city,
            'number' => $validated['busNumber'] ?? $bus->number,
            'color' => $validated['busColor'] ?? $bus->color,
            'capacity' => $validated['busCapacity'] ?? $bus->capacity,
            'fuel_type' => $validated['fuelType'] ?? $bus->fuel_type,
            'status' => $validated['busStatus'] ?? $bus->status,
            'annual_status' => $validated['busAnnualStatus'] ?? $bus->annual_status,
            'insurance' => $validated['busInsurance'] ?? $bus->insurance,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => (new BusResource($bus->fresh()))->toArray($request),
            'msg' => 'bus updated successfully',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $driverId = $request->user()?->driver?->id;
        if (! $driverId) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'driver not found for this account',
            ], 422);
        }

        $bus = Bus::query()->where('driver_id', $driverId)->first();

        if (! $bus) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'bus not found',
            ], 404);
        }

        $bus->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'bus deleted successfully',
        ]);
    }
}
