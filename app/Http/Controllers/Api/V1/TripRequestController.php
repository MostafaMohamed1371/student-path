<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripRequestController extends Controller
{
    use FormatsParentApiResponse;

    public function index(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        $query = TripRequest::query()->with(['student', 'driver', 'tripHistory'])->latest('id');
        if ($driver) {
            $query->where('driver_id', $driver->id);
        } else {
            $query->where('user_id', $request->user()->id);
        }
        $rows = $query->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return $this->parentSuccess([
            'items' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'trip_history_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! ParentContext::ownsStudent($request->user(), (int) $validated['student_id'])) {
            return $this->parentError('forbidden', null, 403);
        }

        if (! empty($validated['trip_history_id'])) {
            $trip = TripHistory::query()->find((int) $validated['trip_history_id']);
            $allowedSchools = ParentContext::studentsFor($request->user())->pluck('school_id')->unique()->filter();
            if ($trip && $allowedSchools->isNotEmpty() && ! $allowedSchools->contains($trip->school_id)) {
                return $this->parentError('Trip is not in scope for your students.', ['trip' => ['Out of scope']], 422);
            }
        }

        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $driverId = Driver::query()
            ->where('school_id', $student->school_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id');

        $row = TripRequest::query()->create([
            'user_id' => $request->user()->id,
            'student_id' => $validated['student_id'],
            'driver_id' => $driverId,
            'trip_history_id' => $validated['trip_history_id'] ?? null,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->parentSuccess(
            $row->load(['student', 'driver', 'tripHistory']),
            'Trip request created',
            201
        );
    }

    public function show(Request $request, TripRequest $trip_request): JsonResponse
    {
        if (! $this->canAccessTripRequest($request, $trip_request)) {
            return $this->parentError('forbidden', null, 403);
        }

        return $this->parentSuccess($trip_request->load(['student', 'driver', 'tripHistory']));
    }

    public function cancel(Request $request, TripRequest $trip_request): JsonResponse
    {
        if ((int) $trip_request->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        if ($trip_request->status === 'cancelled') {
            return $this->parentSuccess($trip_request, 'Already cancelled');
        }

        if ($trip_request->status !== 'pending') {
            return $this->parentError('Only pending trip requests can be cancelled.', null, 422);
        }

        $trip_request->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $this->parentSuccess($trip_request->fresh(), 'Trip request cancelled');
    }

    public function update(Request $request, TripRequest $trip_request): JsonResponse
    {
        if (! $this->canAccessTripRequest($request, $trip_request)) {
            return $this->parentError('forbidden', null, 403);
        }

        $driver = $this->currentDriver($request);
        if ($driver && (int) $trip_request->driver_id === (int) $driver->id) {
            if ($trip_request->status !== 'pending') {
                return $this->parentError('Only pending trip requests can be updated.', null, 422);
            }

            $validated = $request->validate([
                'status' => ['required', 'string', 'in:accepted,rejected'],
            ]);

            DB::transaction(function () use ($trip_request, $validated): void {
                $trip_request->update(['status' => $validated['status']]);
                if ($validated['status'] === 'accepted') {
                    $this->createTripHistoryForAcceptedRequest($trip_request->fresh(['user.homeLocation', 'student.school']));
                }
            });

            return $this->parentSuccess(
                $trip_request->fresh()->load(['student', 'driver', 'tripHistory']),
                'Trip request status updated'
            );
        }

        if ($trip_request->status !== 'pending') {
            return $this->parentError('Only pending trip requests can be updated.', null, 422);
        }

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'trip_history_id' => ['sometimes', 'nullable', 'integer', 'exists:trip_histories,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['student_id'])
            && ! ParentContext::ownsStudent($request->user(), (int) $validated['student_id'])) {
            return $this->parentError('forbidden', null, 403);
        }

        $newTripHistoryId = array_key_exists('trip_history_id', $validated)
            ? $validated['trip_history_id']
            : $trip_request->trip_history_id;

        if ($newTripHistoryId !== null) {
            $trip = TripHistory::query()->find((int) $newTripHistoryId);
            $allowedSchools = ParentContext::studentsFor($request->user())->pluck('school_id')->unique()->filter();
            if ($trip && $allowedSchools->isNotEmpty() && ! $allowedSchools->contains($trip->school_id)) {
                return $this->parentError('Trip is not in scope for your students.', ['trip' => ['Out of scope']], 422);
            }
        }

        $trip_request->fill($validated)->save();

        return $this->parentSuccess($trip_request->fresh()->load(['student', 'driver', 'tripHistory']), 'Trip request updated');
    }

    public function destroy(Request $request, TripRequest $trip_request): JsonResponse
    {
        if ((int) $trip_request->user_id !== (int) $request->user()->id) {
            return $this->parentError('forbidden', null, 403);
        }

        if ($trip_request->status !== 'pending') {
            return $this->parentError('Only pending trip requests can be deleted.', null, 422);
        }

        $trip_request->delete();

        return $this->parentSuccess((object) [], 'Trip request deleted');
    }

    private function createTripHistoryForAcceptedRequest(TripRequest $tripRequest): void
    {
        $student = $tripRequest->student;
        $user = $tripRequest->user;
        if (! $student || ! $user) {
            return;
        }

        $home = $user->homeLocation;
        $school = $student->school;
        $from = $home?->formatted_address
            ?? (isset($home?->latitude, $home?->longitude) ? ($home->latitude.', '.$home->longitude) : null)
            ?? 'Guardian home';
        $to = $school?->name_en
            ?? $school?->name_ar
            ?? $school?->address
            ?? (isset($school?->latitude, $school?->longitude) ? ($school->latitude.', '.$school->longitude) : null)
            ?? 'School';

        $trip = TripHistory::query()->create([
            'school_id' => $student->school_id,
            'route_title' => 'Trip Request #'.$tripRequest->id,
            'location' => 'From '.$from.' to '.$to,
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'PENDING',
            'note' => $tripRequest->notes,
            'students_preview' => [[
                'id' => (string) $student->id,
                'name' => (string) ($student->full_name ?? ''),
            ]],
        ]);

        $tripRequest->forceFill(['trip_history_id' => $trip->id])->save();
    }

    private function currentDriver(Request $request): ?Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->first();
    }

    private function canAccessTripRequest(Request $request, TripRequest $tripRequest): bool
    {
        if ((int) $tripRequest->user_id === (int) $request->user()->id) {
            return true;
        }

        $driver = $this->currentDriver($request);

        return $driver && (int) $tripRequest->driver_id === (int) $driver->id;
    }
}
