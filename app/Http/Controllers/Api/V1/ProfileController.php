<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parent profile: GET/PUT/DELETE /api/profile (delegates to user profile logic).
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return app(UserProfileController::class)->show($request);
    }

    public function update(UpdateUserProfileRequest $request): JsonResponse
    {
        return app(UserProfileController::class)->update($request);
    }

    public function destroy(Request $request): JsonResponse
    {
        return app(UserProfileController::class)->destroy($request);
    }
}
