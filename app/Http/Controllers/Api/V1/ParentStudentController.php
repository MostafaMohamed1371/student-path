<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\TripRequest;
use App\Services\Trips\StudentTripStatusResolver;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentStudentController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly StudentTripStatusResolver $tripStatusResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = ParentContext::studentIdsFor($user);

        if ($studentIds !== []) {
            $students = Student::query()
                ->whereIn('id', $studentIds)
                ->with(['school', 'guardian'])
                ->latest('id')
                ->get();
        } elseif (ParentContext::guardian($user) instanceof Guardian) {
            $students = collect();
        } else {
            $q = Student::query()->with(['school', 'guardian']);
            $this->applyApiScopeBySchoolIdColumn($q, $user);
            $students = $q->latest('id')->get();
        }

        $data = $students->map(function (Student $s) use ($request): array {
            $base = (new StudentResource($s))->toArray($request);
            $base['current_trip'] = $this->tripStatusResolver->tripPayload(
                $this->tripStatusResolver->currentTripForStudent($s)
            );

            return $base;
        })->values()->all();

        return $this->parentSuccess($data);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureParentCanViewStudent($request, $student)) {
            return $resp;
        }

        $student->load(['school', 'guardian']);
        $base = (new StudentResource($student))->toArray($request);
        $base['current_trip'] = $this->tripStatusResolver->tripPayload(
            $this->tripStatusResolver->currentTripForStudent($student)
        );

        return $this->parentSuccess($base);
    }

    public function store(Request $request): JsonResponse
    {
        $guardian = ParentContext::guardian($request->user());
        if (! $guardian instanceof Guardian) {
            return $this->parentError(
                'Link your account to a guardian record (matching phone or guardian_id) to add students.',
                null,
                403
            );
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'grade' => ['required', 'string', 'max:64'],
            'student_phone' => ['required', 'string', 'max:32'],
            'relationship' => ['required', 'string', 'max:64'],
            'district_area' => ['nullable', 'string', 'max:255'],
            'nearest_landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'status' => ['required', 'string', 'max:32'],
        ]);

        $student = DB::transaction(function () use ($guardian, $validated, $request): Student {
            $student = Student::query()->create([
                'school_id' => $guardian->school_id,
                'guardian_id' => $guardian->id,
                'full_name' => $validated['full_name'],
                'gender' => $validated['gender'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'age' => $validated['age'] ?? null,
                'grade' => $validated['grade'],
                'student_phone' => $validated['student_phone'],
                'guardian_name' => $guardian->full_name,
                'guardian_primary_phone' => $guardian->phone,
                'guardian_backup_phone' => $guardian->backup_phone,
                'relationship' => $validated['relationship'],
                'district_area' => $validated['district_area'] ?? null,
                'nearest_landmark' => $validated['nearest_landmark'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'status' => $validated['status'],
            ]);

            TripRequest::query()->create([
                'user_id' => $request->user()->id,
                'student_id' => $student->id,
                'driver_id' => $this->assignSchoolDriverId($student),
                'trip_history_id' => null,
                'status' => 'pending',
                'notes' => $this->autoTripRequestNotes($request, $student),
            ]);

            return $student;
        });

        return $this->parentSuccess(
            (new StudentResource($student->load(['school', 'guardian'])))->toArray($request),
            'Student created',
            201
        );
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureParentCanMutateStudent($request, $student)) {
            return $resp;
        }

        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'string', 'max:32'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            'grade' => ['sometimes', 'string', 'max:64'],
            'student_phone' => ['sometimes', 'string', 'max:32'],
            'relationship' => ['sometimes', 'string', 'max:64'],
            'district_area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nearest_landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'status' => ['sometimes', 'string', 'max:32'],
        ]);

        $guardian = ParentContext::guardian($request->user());
        if ($guardian instanceof Guardian && (int) $student->guardian_id === (int) $guardian->id) {
            $validated['guardian_name'] = $guardian->full_name;
            $validated['guardian_primary_phone'] = $guardian->phone;
            $validated['guardian_backup_phone'] = $guardian->backup_phone;
        }

        $student->fill($validated)->save();

        $student->load(['school', 'guardian']);
        $base = (new StudentResource($student))->toArray($request);
        $base['current_trip'] = $this->tripStatusResolver->tripPayload(
            $this->tripStatusResolver->currentTripForStudent($student)
        );

        return $this->parentSuccess($base, 'Student updated');
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        if ($resp = $this->ensureParentCanMutateStudent($request, $student)) {
            return $resp;
        }

        $student->delete();

        return $this->parentSuccess((object) [], 'Student deleted');
    }

    private function ensureParentCanViewStudent(Request $request, Student $student): ?JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
            return $resp;
        }
        if ($this->isApiAdmin($request->user())) {
            return null;
        }
        if (ParentContext::guardian($request->user()) instanceof Guardian) {
            if (! ParentContext::ownsStudent($request->user(), (int) $student->id)) {
                return $this->parentError('forbidden', null, 403);
            }
        }

        return null;
    }

    private function ensureParentCanMutateStudent(Request $request, Student $student): ?JsonResponse
    {
        $user = $request->user();
        if ($this->isApiAdmin($user)) {
            return null;
        }
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($user, (int) $student->school_id)) {
            return $resp;
        }
        if (! ParentContext::ownsStudent($user, (int) $student->id)) {
            return $this->parentError('forbidden', null, 403);
        }

        return null;
    }

    private function autoTripRequestNotes(Request $request, Student $student): string
    {
        $student->loadMissing('school');
        $school = $student->school;
        $home = $request->user()->homeLocation;

        $studentPoint = isset($student->latitude, $student->longitude)
            ? ($student->latitude.', '.$student->longitude)
            : 'unknown';
        $schoolPoint = isset($school?->latitude, $school?->longitude)
            ? ($school->latitude.', '.$school->longitude)
            : 'unknown';
        $schoolName = $school?->name_en ?? $school?->name_ar ?? 'school';
        $homePoint = isset($home?->latitude, $home?->longitude)
            ? ($home->latitude.', '.$home->longitude)
            : 'unknown';

        return sprintf(
            'Auto-created on student registration. Student location: %s. School: %s (%s). Guardian home: %s.',
            $studentPoint,
            $schoolName,
            $schoolPoint,
            $homePoint
        );
    }

    private function assignSchoolDriverId(Student $student): ?int
    {
        return Driver::query()
            ->where('school_id', $student->school_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id');
    }
}
