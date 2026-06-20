<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangeLanguageRequest;
use App\Http\Requests\Api\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\Student;
use App\Services\Phone\PhoneNormalizer;
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
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('profiles', 'public');
        }

        $attrs = [
            'name' => $validated['name'] ?? $user->name,
            'image' => $validated['image'] ?? $user->image,
            'city' => $validated['city'] ?? $user->city,
            'licence_number' => $validated['licenceNumber'] ?? $user->licence_number,
            'votes' => $validated['votes'] ?? $user->votes,
            'rate' => $validated['rate'] ?? $user->rate,
            'is_verified' => $validated['isVerified'] ?? $user->is_verified,
        ];
        if (array_key_exists('schoolId', $validated)) {
            $attrs['school_id'] = $validated['schoolId'];
        }
        if (array_key_exists('email', $validated)) {
            $attrs['email'] = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $attrs['phone'] = app(PhoneNormalizer::class)->normalize($validated['phone']);
        }

        $user->fill($attrs)->save();
        $this->syncLinkedGuardianPhone($user);

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

    private function syncLinkedGuardianPhone(\App\Models\User $user): void
    {
        if (! $user->wasChanged('phone') || $user->guardian_id === null) {
            return;
        }

        $user->loadMissing('guardian');
        $guardian = $user->guardian;
        if ($guardian === null) {
            return;
        }

        $nationalPhone = substr((string) $user->phone, 3);
        if ($guardian->phone === $nationalPhone) {
            return;
        }

        $guardian->update(['phone' => $nationalPhone]);
        Student::query()
            ->where('guardian_id', $guardian->id)
            ->update(['guardian_primary_phone' => $nationalPhone]);
    }
}
