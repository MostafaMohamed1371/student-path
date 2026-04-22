<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSchoolRequest;
use App\Http\Requests\Api\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    public function index(): JsonResponse
    {
        $schools = School::query()->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => SchoolResource::collection($schools)->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function show(School $school): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => (new SchoolResource($school))->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $school = School::query()->create([
            'name_ar' => $validated['schoolNameAr'],
            'name_en' => $validated['schoolNameEn'],
            'province' => $validated['province'],
            'district' => $validated['district'],
            'address' => $validated['address'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => $validated['status'],
            'principal_name' => $validated['principalName'] ?? null,
            'admin_phone' => $validated['adminPhone'] ?? null,
            'authorized_person_name' => $validated['authorizedPersonName'] ?? null,
            'authorized_person_phone' => $validated['authorizedPersonPhone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'attachment' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('schools', 'public')
                : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => (new SchoolResource($school))->toArray($request),
            'msg' => 'school created successfully',
        ], 201);
    }

    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        $validated = $request->validated();

        $payload = [
            'name_ar' => $validated['schoolNameAr'] ?? $school->name_ar,
            'name_en' => $validated['schoolNameEn'] ?? $school->name_en,
            'province' => $validated['province'] ?? $school->province,
            'district' => $validated['district'] ?? $school->district,
            'address' => $validated['address'] ?? $school->address,
            'latitude' => $validated['latitude'] ?? $school->latitude,
            'longitude' => $validated['longitude'] ?? $school->longitude,
            'status' => $validated['status'] ?? $school->status,
            'principal_name' => $validated['principalName'] ?? $school->principal_name,
            'admin_phone' => $validated['adminPhone'] ?? $school->admin_phone,
            'authorized_person_name' => $validated['authorizedPersonName'] ?? $school->authorized_person_name,
            'authorized_person_phone' => $validated['authorizedPersonPhone'] ?? $school->authorized_person_phone,
            'notes' => $validated['notes'] ?? $school->notes,
        ];

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        $school->update($payload);

        return response()->json([
            'success' => true,
            'data' => (new SchoolResource($school->fresh()))->toArray($request),
            'msg' => 'school updated successfully',
        ]);
    }

    public function destroy(School $school): JsonResponse
    {
        $school->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'school deleted successfully',
        ]);
    }
}
