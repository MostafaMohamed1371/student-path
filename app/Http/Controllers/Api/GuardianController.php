<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGuardianRequest;
use App\Http\Requests\Api\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Phone\DashboardPhoneUserProvisioner;
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
        $validated = $request->validated();
        if ($resp = $this->ensureApiCanMutateSchoolRoster($request->user(), (int) $validated['schoolId'])) {
            return $resp;
        }

        $guardian = Guardian::query()->create([
            'school_id' => $validated['schoolId'],
            'full_name' => $validated['fullName'],
            'phone' => $validated['phone'],
            'backup_phone' => $validated['backupPhone'] ?? null,
            'id_card_number' => $validated['idCardNumber'] ?? null,
            'status' => $validated['status'],
        ])->loadCount('students');
        $this->syncGuardianUser($guardian, app(DashboardPhoneUserProvisioner::class));

        return response()->json([
            'success' => true,
            'data' => (new GuardianResource($guardian))->toArray($request),
            'msg' => 'guardian created successfully',
        ], 201);
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        $validated = $request->validated();
        $targetSchool = (int) ($validated['schoolId'] ?? $guardian->school_id);
        if ($resp = $this->ensureApiCanMutateSchoolRoster($request->user(), $targetSchool)) {
            return $resp;
        }

        $guardian->update([
            'school_id' => $validated['schoolId'] ?? $guardian->school_id,
            'full_name' => $validated['fullName'] ?? $guardian->full_name,
            'phone' => $validated['phone'] ?? $guardian->phone,
            'backup_phone' => $validated['backupPhone'] ?? $guardian->backup_phone,
            'id_card_number' => $validated['idCardNumber'] ?? $guardian->id_card_number,
            'status' => $validated['status'] ?? $guardian->status,
        ]);
        $this->syncGuardianUser($guardian->fresh(), app(DashboardPhoneUserProvisioner::class));

        return response()->json([
            'success' => true,
            'data' => (new GuardianResource($guardian->fresh()->loadCount('students')))->toArray($request),
            'msg' => 'guardian updated successfully',
        ]);
    }

    public function destroy(Request $request, Guardian $guardian): JsonResponse
    {
        if ($resp = $this->ensureApiCanMutateSchoolRoster($request->user(), (int) $guardian->school_id)) {
            return $resp;
        }
        $guardian->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'guardian deleted successfully',
        ]);
    }

    private function syncGuardianUser(Guardian $guardian, DashboardPhoneUserProvisioner $provisioner): void
    {
        $provisioner->upsertGuardian(
            $guardian,
            (string) $guardian->phone,
            (string) $guardian->full_name,
        );
    }
}
