<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangeLanguageRequest;
use App\Http\Requests\Api\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('driver');

        return response()->json([
            'success' => true,
            'data' => (new UserProfileResource($user))->toArray($request),
            'msg' => 'success',
        ]);
    }

    public function update(UpdateUserProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->load('driver');
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('profiles', 'public');
        }

        $user->fill([
            'name' => $validated['name'] ?? $user->name,
            'image' => $validated['image'] ?? $user->image,
            'city' => $validated['city'] ?? $user->city,
            'licence_number' => $validated['licenceNumber'] ?? $user->licence_number,
            'votes' => $validated['votes'] ?? $user->votes,
            'rate' => $validated['rate'] ?? $user->rate,
            'is_verified' => $validated['isVerified'] ?? $user->is_verified,
        ])->save();

        if ($user->driver) {
            $driverPayload = [];

            if (array_key_exists('name', $validated) && is_string($validated['name'])) {
                $parts = preg_split('/\s+/', trim($validated['name'])) ?: [];
                $driverPayload['first_name'] = $parts[0] ?? $user->driver->first_name;
                $driverPayload['father_name'] = $parts[1] ?? $user->driver->father_name;
                $driverPayload['last_name'] = $parts[2] ?? $user->driver->last_name;
            }
            if (array_key_exists('city', $validated)) {
                $driverPayload['residential_address'] = $validated['city'];
            }
            if (array_key_exists('licenceNumber', $validated)) {
                $driverPayload['license_number'] = $validated['licenceNumber'];
            }

            if ($driverPayload !== []) {
                $user->driver->update($driverPayload);
            }
        }

        return response()->json([
            'success' => true,
            'data' => (new UserProfileResource($user->fresh()->load('driver')))->toArray($request),
            'msg' => 'profile updated successfully',
        ]);
    }

    public function changeLanguage(ChangeLanguageRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'preferred_language' => $request->validated('language'),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'language updated successfully',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'account deleted successfully',
        ]);
    }
}
