<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HomeLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeLocationController extends Controller
{
    use FormatsParentApiResponse;

    public function show(Request $request): JsonResponse
    {
        $loc = HomeLocation::query()->where('user_id', $request->user()->id)->first();

        return $this->parentSuccess($loc ? [
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'formatted_address' => $loc->formatted_address,
            'place_id' => $loc->place_id,
        ] : null);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'formatted_address' => ['nullable', 'string', 'max:2000'],
            'place_id' => ['nullable', 'string', 'max:255'],
        ]);

        $loc = HomeLocation::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'formatted_address' => $validated['formatted_address'] ?? null,
                'place_id' => $validated['place_id'] ?? null,
            ]
        );

        return $this->parentSuccess([
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'formatted_address' => $loc->formatted_address,
            'place_id' => $loc->place_id,
        ], 'Home location saved', 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        HomeLocation::query()->where('user_id', $request->user()->id)->delete();

        return $this->parentSuccess((object) [], 'Home location removed');
    }
}
