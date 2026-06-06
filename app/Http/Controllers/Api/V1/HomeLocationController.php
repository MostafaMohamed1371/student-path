<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpsertHomeLocationRequest;
use App\Models\HomeLocation;
use App\Services\HomeLocation\HomeLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeLocationController extends Controller
{
    use FormatsParentApiResponse;

    public function show(Request $request, HomeLocationService $homeLocationService): JsonResponse
    {
        $location = HomeLocation::query()->where('user_id', $request->user()->id)->first();

        return $this->parentSuccess($homeLocationService->formatForApi($location));
    }

    public function store(UpsertHomeLocationRequest $request, HomeLocationService $homeLocationService): JsonResponse
    {
        return $this->persistHomeLocation($request, $homeLocationService, 201);
    }

    public function update(UpsertHomeLocationRequest $request, HomeLocationService $homeLocationService): JsonResponse
    {
        return $this->persistHomeLocation($request, $homeLocationService, 200);
    }

    public function destroy(Request $request, HomeLocationService $homeLocationService): JsonResponse
    {
        $homeLocationService->deleteForUser($request->user());

        return $this->parentSuccess((object) [], 'Home location removed');
    }

    private function persistHomeLocation(
        UpsertHomeLocationRequest $request,
        HomeLocationService $homeLocationService,
        int $status,
    ): JsonResponse {
        $validated = $request->validated();

        $location = $homeLocationService->syncForUser(
            $request->user(),
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated['formatted_address'] ?? null,
            $validated['district_area'] ?? null,
            $validated['nearest_landmark'] ?? null,
            $validated['place_id'] ?? null,
        );

        return $this->parentSuccess(
            $homeLocationService->formatForApi($location),
            'Home location saved',
            $status,
        );
    }
}
