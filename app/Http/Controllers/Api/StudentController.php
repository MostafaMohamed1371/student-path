<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreStudentRequest;
use App\Http\Requests\Api\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use AppliesApiSchoolScoping;

    public function index(Request $request): JsonResponse
    {
        $q = Student::query()->with(['school', 'guardian']);
        $this->applyApiScopeBySchoolIdColumn($q, $request->user());
        $students = $q->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => StudentResource::collection($students)->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => (new StudentResource($student->load(['school', 'guardian'])))->toArray(request()),
            'msg' => 'success',
        ]);
    }

    public function store(StoreStudentRequest $request, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        $validated = $request->validated();
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $validated['schoolId'])) {
            return $resp;
        }
        $guardian = Guardian::query()->findOrFail($validated['guardianId']);
        if ((int) $guardian->school_id !== (int) $validated['schoolId']) {
            return $this->apiForbiddenResponse('forbidden');
        }

        $student = Student::query()->create([
            'school_id' => $validated['schoolId'],
            'guardian_id' => $validated['guardianId'],
            'full_name' => $validated['fullName'],
            'gender' => $validated['gender'],
            'date_of_birth' => $validated['dateOfBirth'] ?? null,
            'age' => $validated['age'] ?? null,
            'profile_photo' => $request->hasFile('profilePhoto')
                ? $request->file('profilePhoto')->store('profiles', 'public')
                : null,
            'grade' => $validated['grade'],
            'student_phone' => $validated['studentPhone'],
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'guardian_backup_phone' => $guardian->backup_phone,
            'relationship' => $validated['relationship'],
            'district_area' => $validated['districtArea'],
            'nearest_landmark' => $validated['nearestLandmark'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => $validated['status'],
        ]);
        $this->syncStudentUser($student, $phoneNormalizer);

        return response()->json([
            'success' => true,
            'data' => (new StudentResource($student->load(['school', 'guardian'])))->toArray($request),
            'msg' => 'student created successfully',
        ], 201);
    }

    public function update(UpdateStudentRequest $request, Student $student, PhoneNormalizer $phoneNormalizer): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
            return $resp;
        }
        $validated = $request->validated();
        if (array_key_exists('schoolId', $validated)) {
            if ($r = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $validated['schoolId'])) {
                return $r;
            }
        }
        $targetSchool = (int) ($validated['schoolId'] ?? $student->school_id);
        $guardian = null;
        if (isset($validated['guardianId'])) {
            $guardian = Guardian::query()->findOrFail($validated['guardianId']);
            if ((int) $guardian->school_id !== $targetSchool) {
                return $this->apiForbiddenResponse('forbidden');
            }
        }

        $payload = [
            'school_id' => $validated['schoolId'] ?? $student->school_id,
            'guardian_id' => $validated['guardianId'] ?? $student->guardian_id,
            'full_name' => $validated['fullName'] ?? $student->full_name,
            'gender' => $validated['gender'] ?? $student->gender,
            'date_of_birth' => $validated['dateOfBirth'] ?? $student->date_of_birth,
            'age' => $validated['age'] ?? $student->age,
            'grade' => $validated['grade'] ?? $student->grade,
            'student_phone' => $validated['studentPhone'] ?? $student->student_phone,
            'relationship' => $validated['relationship'] ?? $student->relationship,
            'district_area' => $validated['districtArea'] ?? $student->district_area,
            'nearest_landmark' => $validated['nearestLandmark'] ?? $student->nearest_landmark,
            'latitude' => $validated['latitude'] ?? $student->latitude,
            'longitude' => $validated['longitude'] ?? $student->longitude,
            'status' => $validated['status'] ?? $student->status,
        ];

        if ($guardian) {
            $payload['guardian_name'] = $guardian->full_name;
            $payload['guardian_primary_phone'] = $guardian->phone;
            $payload['guardian_backup_phone'] = $guardian->backup_phone;
        }

        if ($request->hasFile('profilePhoto')) {
            $payload['profile_photo'] = $request->file('profilePhoto')->store('profiles', 'public');
        }

        $student->update($payload);
        $this->syncStudentUser($student->fresh(), $phoneNormalizer);

        return response()->json([
            'success' => true,
            'data' => (new StudentResource($student->fresh()->load(['school', 'guardian'])))->toArray($request),
            'msg' => 'student updated successfully',
        ]);
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
            return $resp;
        }
        $student->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'student deleted successfully',
        ]);
    }

    private function syncStudentUser(Student $student, PhoneNormalizer $phoneNormalizer): void
    {
        if (! $phoneNormalizer->isValidIraqiMobile((string) $student->student_phone)) {
            return;
        }

        User::query()->firstOrCreate(
            ['phone' => $phoneNormalizer->normalize((string) $student->student_phone)],
            [
                'name' => $student->full_name,
                'school_id' => $student->school_id,
                'password' => config('dashboard.seed_password'),
                'is_active' => $student->status === 'active',
                'phone_verified_at' => now(),
            ]
        );
    }
}
