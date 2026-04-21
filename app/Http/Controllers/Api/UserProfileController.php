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
        return response()->json([
            'success' => true,
            'data' => (new UserProfileResource($request->user()))->toArray($request),
            'msg' => 'success',
        ]);
    }

    public function update(UpdateUserProfileRequest $request): JsonResponse
    {
        $user = $request->user();
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

        return response()->json([
            'success' => true,
            'data' => (new UserProfileResource($user->fresh()))->toArray($request),
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
