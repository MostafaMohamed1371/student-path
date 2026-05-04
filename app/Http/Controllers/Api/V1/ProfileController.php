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

    public function update(Request $request): JsonResponse
    {
        $form = UpdateUserProfileRequest::createFrom($request);
        $form->setUserResolver($request->getUserResolver());
        $form->setRouteResolver($request->getRouteResolver());
        $form->setContainer(app());
        $form->validateResolved();

        return app(UserProfileController::class)->update($form);
    }

    public function destroy(Request $request): JsonResponse
    {
        return app(UserProfileController::class)->destroy($request);
    }
}
