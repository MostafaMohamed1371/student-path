<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DelayReasonType;
use App\Enums\StudentTripStopStatus;
use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DelayAlert;
use App\Models\Driver;
use App\Models\InAppNotification;
use App\Models\SosAlert;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\DriverTripModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DriverTripController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly DriverTripModuleService $driverTripModuleService,
    ) {}

    public function scheduledTrips(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can list scheduled trips.', null, 403);
        }

        $items = $this->driverTripModuleService->scheduledTripsForDriverList($driver);

        return $this->parentSuccess($items, 'all trips');
    }

    public function driverOverview(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can access this endpoint.', null, 403);
        }

        $data = $this->driverTripModuleService->driverOverview($driver);

        return $this->parentSuccess($data, 'Retrieve driver dashboard');
    }

    public function tripDetails(Request $request, string $trip): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can view trip details.', null, 403);
        }

        $tripPk = $this->driverTripModuleService->parseTripPublicId($trip);
        if ($tripPk === null) {
            return $this->parentError('Invalid trip id.', null, 422);
        }

        $record = TripHistory::query()->find($tripPk);
        if (! $record) {
            return $this->parentError('Trip not found.', null, 404);
        }

        if ($record->driver_id === null || (int) $record->driver_id !== (int) $driver->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $data = $this->driverTripModuleService->tripDetailPayload($record);

        return $this->parentSuccess($data, 'تم جلب بيانات الرحلة');
    }

    public function currentTrip(Request $request): JsonResponse
    {
        $request->validate([
            'shift_period' => ['nullable', 'string', 'max:32'],
        ]);

        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can access this endpoint.', null, 403);
        }

        $targetShift = $this->resolveShiftFilter((string) $request->query('shift_period', ''));
        if ($request->filled('shift_period') && $targetShift === null) {
            return $this->parentError('Invalid shift_period. Use MORNING/EVENING or صباح/مساء.', null, 422);
        }

        $trip = $this->driverTripModuleService->activeTripForDriverByShift($driver, $targetShift);
        $payload = $this->driverTripModuleService->currentTripPayload($trip);

        if ($payload === null) {
            return $this->parentSuccess(null, 'No active trip.');
        }

        return $this->parentSuccess($payload, 'success');
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can update trip status.', null, 403);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'string'],
            'new_status' => ['required', 'string', 'in:ON_WAY,ARRIVED,BOARDED,ABSENT'],
            'driver_lat' => ['nullable', 'numeric'],
            'driver_lng' => ['nullable', 'numeric'],
        ]);

        $studentPk = $this->driverTripModuleService->parseStudentPublicId($validated['student_id']);
        if ($studentPk === null) {
            return $this->parentError('Invalid student_id.', null, 422);
        }

        $newStatus = StudentTripStopStatus::tryFrom($validated['new_status']);
        if (! $newStatus instanceof StudentTripStopStatus) {
            return $this->parentError('Invalid new_status.', null, 422);
        }

        $trip = $this->driverTripModuleService->activeTripForDriver($driver);
        if (! $trip) {
            return $this->parentError('No active trip.', null, 422);
        }

        $result = $this->driverTripModuleService->applyStudentStatusChange(
            $trip,
            $driver,
            $studentPk,
            $newStatus,
            isset($validated['driver_lat']) ? (float) $validated['driver_lat'] : null,
            isset($validated['driver_lng']) ? (float) $validated['driver_lng'] : null,
        );

        if (! $result['success']) {
            $data = null;
            if (isset($result['waiting_min'], $result['less_than_50_meter'])) {
                $data = [
                    'waiting_min' => $result['waiting_min'],
                    'less_than_50_meter' => $result['less_than_50_meter'],
                ];
            }

            $http = match ($result['message'] ?? '') {
                'forbidden' => 403,
                default => 422,
            };

            return $this->parentError($result['message'], null, $http, $data);
        }

        return $this->parentSuccess([
            'waiting_min' => $result['waiting_min'] ?? 0,
            'less_than_50_meter' => $result['less_than_50_meter'] ?? false,
        ], $result['message']);
    }

    public function endTrip(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can end a trip.', null, 403);
        }

        $result = $this->driverTripModuleService->endActiveTrip($driver);
        if (! $result['success']) {
            return $this->parentError($result['message'], null, 422);
        }

        return $this->parentSuccess([
            'trip_id' => $result['trip_id'],
            'status' => 'COMPLETED',
            'ended_at' => $result['ended_at'],
        ], $result['message']);
    }

    public function finalizeTrip(Request $request, string $trip): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can finalize trips.', null, 403);
        }

        $validated = $request->validate([
            'trip_id' => ['required', 'string', 'max:64'],
            'driver_notes' => ['nullable', 'string', 'max:4000'],
            'final_lat' => ['required', 'numeric', 'between:-90,90'],
            'final_lng' => ['required', 'numeric', 'between:-180,180'],
            'end_timestamp' => ['required', 'date'],
        ]);

        $tripPkFromPath = $this->driverTripModuleService->parseTripPublicId($trip);
        $tripPkFromBody = $this->driverTripModuleService->parseTripPublicId((string) $validated['trip_id']);
        if ($tripPkFromPath === null || $tripPkFromBody === null) {
            return $this->parentError('Invalid trip id.', null, 422);
        }
        if ($tripPkFromPath !== $tripPkFromBody) {
            return $this->parentError('trip_id in path and body must match.', null, 422);
        }

        $result = $this->driverTripModuleService->finalizeTripForDriver(
            $driver,
            $tripPkFromPath,
            isset($validated['driver_notes']) ? (string) $validated['driver_notes'] : null,
            (float) $validated['final_lat'],
            (float) $validated['final_lng'],
            Carbon::parse((string) $validated['end_timestamp']),
        );

        if (! ($result['success'] ?? false)) {
            return $this->parentError(
                (string) ($result['message'] ?? 'Unable to finalize trip.'),
                null,
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->parentSuccess((object) [], (string) $result['message']);
    }

    public function sendDelayAlert(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can send delay alerts.', null, 403);
        }

        $validated = $request->validate([
            'trip_id' => ['required', 'string', 'max:64'],
            'reason_type' => ['required', 'string', 'in:TRAFFIC,MECHANICAL_ISSUES,STUDENT_DELAY,OTHER'],
            'delay_duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:reason_type,OTHER'],
            'driver_lat' => ['required', 'numeric', 'between:-90,90'],
            'driver_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $tripPk = $this->driverTripModuleService->parseTripPublicId((string) $validated['trip_id']);
        if ($tripPk === null) {
            return $this->parentError('Invalid trip id.', null, 422);
        }

        $trip = TripHistory::query()->find($tripPk);
        if (! $trip) {
            return $this->parentError('Trip not found.', null, 404);
        }
        if ((int) ($trip->driver_id ?? 0) !== (int) $driver->id) {
            return $this->parentError('forbidden', null, 403);
        }

        DelayAlert::query()->create([
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $request->user()->id,
            'reason_type' => DelayReasonType::from((string) $validated['reason_type'])->value,
            'delay_duration_minutes' => (int) $validated['delay_duration_minutes'],
            'note' => isset($validated['note']) && trim((string) $validated['note']) !== '' ? trim((string) $validated['note']) : null,
            'driver_lat' => (float) $validated['driver_lat'],
            'driver_lng' => (float) $validated['driver_lng'],
        ]);

        $trip->loadMissing('tripHistoryStudents.student');
        $guardianIds = $trip->tripHistoryStudents
            ->map(fn ($ths) => $ths->student?->guardian_id)
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($guardianIds !== []) {
            $users = User::query()->whereIn('guardian_id', $guardianIds)->get();
            foreach ($users as $u) {
                InAppNotification::query()->create([
                    'user_id' => $u->id,
                    'title' => 'تنبيه تأخير الرحلة',
                    'body' => 'تم إرسال بلاغ تأخير لمدة '.$validated['delay_duration_minutes'].' دقيقة',
                    'data' => [
                        'type' => 'DELAY_ALERT',
                        'trip_id' => $this->driverTripModuleService->externalTripId($trip),
                        'reason_type' => (string) $validated['reason_type'],
                        'delay_duration_minutes' => (int) $validated['delay_duration_minutes'],
                    ],
                ]);
            }
        }

        return $this->parentSuccess(null, 'تم إرسال بلاغ التأخير وتنبيه أولياء الأمور بنجاح');
    }

    public function triggerSos(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can trigger SOS.', null, 403);
        }

        $validated = $request->validate([
            'trip_id' => ['required', 'string', 'max:64'],
            'driver_lat' => ['required', 'numeric', 'between:-90,90'],
            'driver_lng' => ['required', 'numeric', 'between:-180,180'],
            'emergency_type' => ['required', 'string', 'in:SOS'],
            'timestamp' => ['required', 'date'],
        ]);

        $tripPk = $this->driverTripModuleService->parseTripPublicId((string) $validated['trip_id']);
        if ($tripPk === null) {
            return $this->parentError('Invalid trip id.', null, 422);
        }
        $trip = TripHistory::query()->find($tripPk);
        if (! $trip) {
            return $this->parentError('Trip not found.', null, 404);
        }
        if ((int) ($trip->driver_id ?? 0) !== (int) $driver->id) {
            return $this->parentError('forbidden', null, 403);
        }

        $triggeredAt = Carbon::parse((string) $validated['timestamp']);

        $sos = SosAlert::query()->create([
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'user_id' => $request->user()->id,
            'emergency_type' => 'SOS',
            'status' => 'TRIGGERED',
            'driver_lat' => (float) $validated['driver_lat'],
            'driver_lng' => (float) $validated['driver_lng'],
            'triggered_at' => $triggeredAt,
        ]);

        $trip->loadMissing('tripHistoryStudents.student');
        $guardianIds = $trip->tripHistoryStudents
            ->map(fn ($ths) => $ths->student?->guardian_id)
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $usersToNotify = User::query()
            ->when($guardianIds !== [], fn ($q) => $q->orWhereIn('guardian_id', $guardianIds))
            ->orWhere(function ($q) use ($trip): void {
                $q->where('is_admin', true)
                    ->where(function ($q2) use ($trip): void {
                        $q2->whereNull('school_id')->orWhere('school_id', $trip->school_id);
                    });
            })
            ->get()
            ->unique('id');

        foreach ($usersToNotify as $u) {
            InAppNotification::query()->create([
                'user_id' => $u->id,
                'title' => 'نداء استغاثة طارئ',
                'body' => 'تم إرسال نداء استغاثة من السائق ويجري تتبع الموقع',
                'data' => [
                    'type' => 'SOS_TRIGGERED',
                    'sos_id' => 'SOS-'.$sos->id,
                    'trip_id' => $this->driverTripModuleService->externalTripId($trip),
                ],
            ]);
        }

        return $this->parentSuccess([
            'sos_id' => 'SOS-'.$sos->id,
            'emergency_numbers' => array_values(array_filter(config('sos.emergency_numbers', []), 'is_array')),
            'tracking_interval_ms' => (int) config('sos.tracking_interval_ms', 5000),
        ], 'تم إرسال نداء الاستغاثة بنجاح، جاري تتبع موقعك');
    }

    public function stopSos(Request $request, string $sos): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can stop SOS.', null, 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'final_lat' => ['required', 'numeric', 'between:-90,90'],
            'final_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $sosId = $this->parseSosId($sos);
        if ($sosId === null) {
            return $this->parentError('Invalid sos_id.', null, 422);
        }

        $row = SosAlert::query()->find($sosId);
        if (! $row) {
            return $this->parentError('SOS record not found.', null, 404);
        }
        if ((int) $row->driver_id !== (int) $driver->id) {
            return $this->parentError('forbidden', null, 403);
        }
        if ((string) $row->status === 'STOPPED') {
            return $this->parentSuccess(null, 'تم إنهاء حالة الطوارئ بنجاح');
        }

        $row->forceFill([
            'status' => 'STOPPED',
            'stopped_at' => now(),
            'stop_reason' => isset($validated['reason']) && trim((string) $validated['reason']) !== '' ? trim((string) $validated['reason']) : null,
            'final_lat' => (float) $validated['final_lat'],
            'final_lng' => (float) $validated['final_lng'],
        ])->save();

        return $this->parentSuccess(null, 'تم إنهاء حالة الطوارئ بنجاح');
    }

    private function currentDriver(Request $request): ?Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->first();
    }

    private function resolveShiftFilter(string $raw): ?string
    {
        $v = trim($raw);
        if ($v === '') {
            return null;
        }
        $u = strtoupper($v);
        if (in_array($u, ['MORNING', 'EVENING'], true)) {
            return $u;
        }

        return app(DriverShiftResolver::class)->fromPresentType($v);
    }

    private function parseSosId(string $raw): ?int
    {
        $v = trim($raw);
        if (preg_match('/^SOS-(\d+)$/i', $v, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d+$/', $v)) {
            return (int) $v;
        }

        return null;
    }
}
