<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGuardianRequest;
use App\Http\Requests\Api\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    use AppliesApiSchoolScoping;

    public function index(Request $request): JsonResponse
    {
        $q = Guardian::query()->withCount('students');
        $this->applyApiScopeBySchoolIdColumn($q, $request->user());
        $guardians = $q->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => GuardianResource::collection($guardians)->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function show(Request $request, Guardian $guardian): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $guardian->school_id)) {
            return $resp;
        }
        $guardian->loadCount('students');

        return response()->json([
            'success' => true,
            'data' => (new GuardianResource($guardian))->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function store(StoreGuardianRequest $request, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        if ($resp = $this->ensureApiAdminForMutations($request->user())) {
            return $resp;
        }
        $validated = $request->validated();

        $guardian = Guardian::query()->create([
            'school_id' => $validated['schoolId'],
            'full_name' => $validated['fullName'],
            'phone' => $validated['phone'],
            'backup_phone' => $validated['backupPhone'] ?? null,
            'id_card_number' => $validated['idCardNumber'] ?? null,
            'status' => $validated['status'],
        ])->loadCount('students');
        $this->syncGuardianUser($guardian, $phoneNormalizer);

        return response()->json([
            'success' => true,
            'data' => (new GuardianResource($guardian))->toArray($request),
            'msg' => 'guardian created successfully',
        ], 201);
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        if ($resp = $this->ensureApiAdminForMutations($request->user())) {
            return $resp;
        }
        $validated = $request->validated();

        $guardian->update([
            'school_id' => $validated['schoolId'] ?? $guardian->school_id,
            'full_name' => $validated['fullName'] ?? $guardian->full_name,
            'phone' => $validated['phone'] ?? $guardian->phone,
            'backup_phone' => $validated['backupPhone'] ?? $guardian->backup_phone,
            'id_card_number' => $validated['idCardNumber'] ?? $guardian->id_card_number,
            'status' => $validated['status'] ?? $guardian->status,
        ]);
        $this->syncGuardianUser($guardian->fresh(), $phoneNormalizer);

        return response()->json([
            'success' => true,
            'data' => (new GuardianResource($guardian->fresh()->loadCount('students')))->toArray($request),
            'msg' => 'guardian updated successfully',
        ]);
    }

    public function destroy(Request $request, Guardian $guardian): JsonResponse
    {
        if ($resp = $this->ensureApiAdminForMutations($request->user())) {
            return $resp;
        }
        $guardian->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'guardian deleted successfully',
        ]);
    }

    private function syncGuardianUser(Guardian $guardian, PhoneNormalizer $phoneNormalizer): void
    {
        if (! $phoneNormalizer->isValidIraqiMobile((string) $guardian->phone)) {
            return;
        }

        User::query()->firstOrCreate(
            ['phone' => $phoneNormalizer->normalize((string) $guardian->phone)],
            [
                'name' => $guardian->full_name,
                'school_id' => $guardian->school_id,
                'password' => config('dashboard.seed_password'),
                'is_active' => $guardian->status === 'active',
                'phone_verified_at' => now(),
            ]
        );
    }
}
