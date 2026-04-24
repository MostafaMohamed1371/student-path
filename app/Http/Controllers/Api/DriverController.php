<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDriverRequest;
use App\Http\Requests\Api\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    use AppliesApiSchoolScoping;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Driver::query()->with('bus');
        $this->applyApiScopeBySchoolIdColumn($q, $user);
        $drivers = $q->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => DriverResource::collection($drivers)->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function show(Request $request, Driver $driver): JsonResponse
    {
        if ($resp = $this->ensureApiCanAccessDriver($request->user(), $driver)) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => (new DriverResource($driver->load('bus')))->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function myDriver(Request $request): JsonResponse
    {
        $driver = Driver::query()->with('bus')->where('user_id', $request->user()->id)->first();
        if (! $driver) {
            return response()->json([
                'success' => false,
                'data' => (object) [],
                'msg' => 'driver not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => (new DriverResource($driver))->toArray($request),
            'msg' => 'success',
        ]);
    }

    public function store(StoreDriverRequest $request, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        $validated = $request->validated();
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $validated['schoolId'])) {
            return $resp;
        }
        $user = $this->resolveDriverUser($validated, $phoneNormalizer);

        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $validated['schoolId'],
            'first_name' => $validated['firstName'],
            'father_name' => $validated['fatherName'],
            'grandfather_name' => $validated['grandfatherName'],
            'last_name' => $validated['lastName'],
            'age' => $validated['age'],
            'id_card_number' => $validated['idCardNumber'],
            'license_number' => $validated['licenseNumber'],
            'primary_phone' => $validated['primaryPhone'],
            'emergency_phone' => $validated['emergencyPhone'],
            'residential_address' => $validated['residentialAddress'],
            'status' => $validated['status'],
            'id_card_image' => $request->hasFile('idCardImage') ? $request->file('idCardImage')->store('drivers', 'public') : null,
            'license_image' => $request->hasFile('licenseImage') ? $request->file('licenseImage')->store('drivers', 'public') : null,
            'non_conviction_certificate' => $request->hasFile('nonConvictionCertificate') ? $request->file('nonConvictionCertificate')->store('drivers', 'public') : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => (new DriverResource($driver))->toArray($request),
            'msg' => 'driver created successfully',
        ], 201);
    }

    public function update(UpdateDriverRequest $request, Driver $driver, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        if ($resp = $this->ensureApiCanAccessDriver($request->user(), $driver)) {
            return $resp;
        }
        $validated = $request->validated();
        if (array_key_exists('schoolId', $validated)) {
            if ($r = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $validated['schoolId'])) {
                return $r;
            }
        }

        $payload = [
            'school_id' => $validated['schoolId'] ?? $driver->school_id,
            'first_name' => $validated['firstName'] ?? $driver->first_name,
            'father_name' => $validated['fatherName'] ?? $driver->father_name,
            'grandfather_name' => $validated['grandfatherName'] ?? $driver->grandfather_name,
            'last_name' => $validated['lastName'] ?? $driver->last_name,
            'age' => $validated['age'] ?? $driver->age,
            'id_card_number' => $validated['idCardNumber'] ?? $driver->id_card_number,
            'license_number' => $validated['licenseNumber'] ?? $driver->license_number,
            'primary_phone' => $validated['primaryPhone'] ?? $driver->primary_phone,
            'emergency_phone' => $validated['emergencyPhone'] ?? $driver->emergency_phone,
            'residential_address' => $validated['residentialAddress'] ?? $driver->residential_address,
            'status' => $validated['status'] ?? $driver->status,
        ];

        if (isset($validated['primaryPhone'])) {
            $user = $this->resolveDriverUser($validated, $phoneNormalizer);
            $payload['user_id'] = $user->id;
        }

        if ($request->hasFile('idCardImage')) {
            $payload['id_card_image'] = $request->file('idCardImage')->store('drivers', 'public');
        }
        if ($request->hasFile('licenseImage')) {
            $payload['license_image'] = $request->file('licenseImage')->store('drivers', 'public');
        }
        if ($request->hasFile('nonConvictionCertificate')) {
            $payload['non_conviction_certificate'] = $request->file('nonConvictionCertificate')->store('drivers', 'public');
        }

        $driver->update($payload);

        return response()->json([
            'success' => true,
            'data' => (new DriverResource($driver->fresh('bus')))->toArray($request),
            'msg' => 'driver updated successfully',
        ]);
    }

    public function destroy(Request $request, Driver $driver): JsonResponse
    {
        if ($resp = $this->ensureApiCanAccessDriver($request->user(), $driver)) {
            return $resp;
        }
        $driver->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'driver deleted successfully',
        ]);
    }

    private function resolveDriverUser(array $validated, PhoneNormalizer $phoneNormalizer): User
    {
        $rawPhone = (string) ($validated['primaryPhone'] ?? '');
        $phone = $phoneNormalizer->normalize($rawPhone);
        $name = trim(
            ($validated['firstName'] ?? '').' '.
            ($validated['fatherName'] ?? '').' '.
            ($validated['lastName'] ?? '')
        );

        return User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $name !== '' ? $name : null,
                'password' => config('dashboard.seed_password'),
                'is_active' => true,
                'phone_verified_at' => now(),
            ]
        );
    }

    private function ensureApiCanAccessDriver(User $user, Driver $driver): ?JsonResponse
    {
        return $this->ensureApiTargetsOwnSchoolOrAdmin($user, (int) $driver->school_id);
    }
}
