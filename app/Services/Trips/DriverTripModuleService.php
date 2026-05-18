<?php

namespace App\Services\Trips;

use App\Enums\ScheduledTripCardStatus;
use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Http\Resources\StudentResource;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use App\Services\TransportLines\TransportDriverCardBuilder;
use App\Support\Geo\Haversine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DriverTripModuleService
{
    public function __construct(
        private readonly TransportDriverCardBuilder $transportDriverCardBuilder,
        private readonly DriverShiftResolver $driverShiftResolver,
    ) {}

    /**
     * @return array{
     *   all_students: int,
     *   all_available_seats: int,
     *   all_unavailable_seats: int,
     *   available_morning_seats: int,
     *   available_evening_seats: int,
     *   shift_period: string|null,
     *   available_seats_for_shift: int|null
     * }
     */
    public function driverOverview(Driver $driver, ?Carbon $onDay = null): array
    {
        $onDay ??= now();
        $tz = config('app.timezone') ?: 'UTC';
        $dayStart = $onDay->copy()->timezone($tz)->startOfDay();
        $dayEnd = $onDay->copy()->timezone($tz)->endOfDay();

        $todayTrips = TripHistory::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->get(['id', 'trip_type', 'start_time']);

        $tripIdsToday = $todayTrips->pluck('id')->all();

        $allStudents = $tripIdsToday === []
            ? 0
            : (int) TripHistoryStudent::query()->whereIn('trip_history_id', $tripIdsToday)->count();

        $driver->loadMissing('bus');
        $capacity = max(0, (int) ($driver->bus?->capacity ?? 0));

        $active = $this->activeTripForDriver($driver);
        $unavailableSeats = 0;
        if ($active) {
            $unavailableSeats = (int) TripHistoryStudent::query()
                ->where('trip_history_id', $active->id)
                ->where('status', StudentTripStopStatus::BOARDED->value)
                ->count();
        }
        $allAvailableSeats = max(0, $capacity - $unavailableSeats);

        $morningTripIds = $todayTrips
            ->filter(fn (TripHistory $t): bool => $this->effectiveTripDayPart($t, $driver, $tz) === DriverShiftResolver::MORNING)
            ->pluck('id')
            ->all();
        $eveningTripIds = $todayTrips
            ->filter(fn (TripHistory $t): bool => $this->effectiveTripDayPart($t, $driver, $tz) === DriverShiftResolver::EVENING)
            ->pluck('id')
            ->all();

        $peakMorningStudents = $this->peakStudentCountOnTrips($morningTripIds);
        $peakEveningStudents = $this->peakStudentCountOnTrips($eveningTripIds);

        $availableMorningSeats = max(0, $capacity - $peakMorningStudents);
        $availableEveningSeats = max(0, $capacity - $peakEveningStudents);

        $rawShift = strtoupper(trim((string) ($driver->shift_period ?? '')));
        $shiftPeriod = in_array($rawShift, [DriverShiftResolver::MORNING, DriverShiftResolver::EVENING], true)
            ? $rawShift
            : null;

        $availableSeatsForShift = match ($shiftPeriod) {
            DriverShiftResolver::MORNING => $availableMorningSeats,
            DriverShiftResolver::EVENING => $availableEveningSeats,
            default => null,
        };

        return [
            'all_students' => $allStudents,
            'all_available_seats' => $allAvailableSeats,
            'all_unavailable_seats' => $unavailableSeats,
            'available_morning_seats' => $availableMorningSeats,
            'available_evening_seats' => $availableEveningSeats,
            'shift_period' => $shiftPeriod,
            'available_seats_for_shift' => $availableSeatsForShift,
        ];
    }

    /**
     * Largest number of students assigned to a single trip in the set (one bus leg).
     *
     * @param  list<int>  $tripIds
     */
    private function peakStudentCountOnTrips(array $tripIds): int
    {
        if ($tripIds === []) {
            return 0;
        }

        $max = TripHistoryStudent::query()
            ->whereIn('trip_history_id', $tripIds)
            ->selectRaw('trip_history_id, COUNT(*) as c')
            ->groupBy('trip_history_id')
            ->pluck('c')
            ->max();

        return (int) ($max ?? 0);
    }

    /**
     * Classify a trip into morning vs evening for seat math.
     *
     * Order: explicit {@see TripType} on the trip → driver's {@see Driver::$shift_period} when
     * {@see TripHistory::$trip_type} is missing (legacy rows follow the driver's configured shift)
     * → clock on {@see TripHistory::$start_time} in the app timezone.
     */
    private function effectiveTripDayPart(TripHistory $trip, Driver $driver, string $tz): string
    {
        $fromType = $this->driverShiftResolver->fromTripType(
            is_string($trip->trip_type) && $trip->trip_type !== '' ? $trip->trip_type : null
        );
        if ($fromType !== null) {
            return $fromType;
        }

        $driverShift = strtoupper(trim((string) ($driver->shift_period ?? '')));
        if ($driverShift === DriverShiftResolver::MORNING || $driverShift === DriverShiftResolver::EVENING) {
            return $driverShift;
        }

        $start = $trip->start_time instanceof Carbon
            ? $trip->start_time->copy()->timezone($tz)
            : Carbon::parse((string) $trip->start_time, $tz)->timezone($tz);
        $hour = (int) $start->format('G');
        if ($hour >= 4 && $hour < 14) {
            return DriverShiftResolver::MORNING;
        }

        return DriverShiftResolver::EVENING;
    }

    /**
     * Driver home: today's trips ordered by start time (scheduled trip cards).
     *
     * @return list<array{id: int, title: string, time: string, status: string, type: string|null}>
     */
    public function scheduledTripsForDriver(Driver $driver, ?Carbon $onDay = null): array
    {
        return $this->scheduledTripsForDriverList($driver, $onDay);
    }

    /**
     * All scheduled trip cards for the driver on the given calendar day (app timezone), ordered by start time.
     *
     * @return list<array{id: int, title: string, time: string, status: string, type: string|null}>
     */
    public function scheduledTripsForDriverList(Driver $driver, ?Carbon $onDay = null): array
    {
        $onDay ??= now();
        $tz = config('app.timezone') ?: 'UTC';
        $dayStart = $onDay->copy()->timezone($tz)->startOfDay();
        $dayEnd = $onDay->copy()->timezone($tz)->endOfDay();

        $trips = TripHistory::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $now = now();

        return $trips->map(function (TripHistory $trip) use ($now, $tz): array {
            $start = $trip->start_time instanceof Carbon
                ? $trip->start_time->copy()->timezone($tz)
                : Carbon::parse((string) $trip->start_time)->timezone($tz);

            return [
                'id' => (int) $trip->id,
                'title' => $this->scheduledTripCardTitle($trip),
                'time' => $this->formatArabicShortTime($start),
                'status' => $this->scheduledTripCardStatus($trip, $now)->value,
                'type' => $this->scheduledTripTypeValue($trip),
            ];
        })->values()->all();
    }

    /**
     * @return array{students_on_trips_today: int, bus_capacity: int|null, vacant_seats: int|null}
     */
    public function driverHomeTripStats(Driver $driver, ?Carbon $onDay = null): array
    {
        $onDay ??= now();
        $tz = config('app.timezone') ?: 'UTC';
        $dayStart = $onDay->copy()->timezone($tz)->startOfDay();
        $dayEnd = $onDay->copy()->timezone($tz)->endOfDay();

        $tripIds = TripHistory::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->pluck('id')
            ->all();

        $studentsOnTripsToday = $tripIds === []
            ? 0
            : (int) TripHistoryStudent::query()->whereIn('trip_history_id', $tripIds)->count();

        $driver->loadMissing('bus');
        $capacity = $driver->bus?->capacity !== null ? (int) $driver->bus->capacity : null;

        $active = $this->activeTripForDriver($driver);
        $onBusCount = 0;
        if ($active) {
            $onBusCount = (int) TripHistoryStudent::query()
                ->where('trip_history_id', $active->id)
                ->where('status', StudentTripStopStatus::BOARDED->value)
                ->count();
        }

        $vacant = $capacity !== null ? max(0, $capacity - $onBusCount) : null;

        return [
            'students_on_trips_today' => $studentsOnTripsToday,
            'bus_capacity' => $capacity,
            'vacant_seats' => $vacant,
        ];
    }

    public function activeTripForDriver(Driver $driver): ?TripHistory
    {
        return $this->activeTripForDriverByShift($driver, null);
    }

    public function activeTripForDriverByShift(Driver $driver, ?string $targetShift): ?TripHistory
    {
        $query = TripHistory::query()->tap(fn (Builder $q) => $this->applyInProgressStartedTripScope($q, $driver));

        if ($targetShift === 'MORNING') {
            $query->whereIn('trip_type', [TripType::MORNING_PICKUP->value, TripType::MORNING_RETURN->value]);
        } elseif ($targetShift === 'EVENING') {
            $query->whereIn('trip_type', [TripType::EVENING_PICKUP->value, TripType::EVENING_RETURN->value]);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public function driverTripStartWindow(TripHistory $trip): array
    {
        $start = $trip->start_time instanceof Carbon
            ? $trip->start_time->copy()
            : Carbon::parse((string) $trip->start_time);

        $early = (int) config('trips.driver_start_early_minutes', 15);
        $late = (int) config('trips.driver_start_late_minutes', 30);

        $windowStart = $start->copy()->subMinutes($early);
        $latestStart = $start->copy()->addMinutes($late);

        $end = $trip->end_time
            ? ($trip->end_time instanceof Carbon
                ? $trip->end_time->copy()
                : Carbon::parse((string) $trip->end_time))
            : null;

        $windowEnd = ($end !== null && $end->lt($latestStart)) ? $end : $latestStart;

        return [$windowStart, $windowEnd];
    }

    public function canDriverStartTripNow(TripHistory $trip, ?Carbon $now = null): bool
    {
        $now ??= now();
        [$windowStart, $windowEnd] = $this->driverTripStartWindow($trip);

        return $now->gte($windowStart) && $now->lte($windowEnd);
    }

    public function otherStartedTripForDriver(Driver $driver, ?int $exceptTripId = null): ?TripHistory
    {
        $query = TripHistory::query()->tap(fn (Builder $q) => $this->applyInProgressStartedTripScope($q, $driver));

        if ($exceptTripId !== null) {
            $query->where('id', '!=', $exceptTripId);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * A trip the driver has started and not finished (COMPLETED/CANCELLED), regardless of scheduled end_time.
     */
    private function applyInProgressStartedTripScope(Builder $query, Driver $driver): void
    {
        $query
            ->where('driver_id', $driver->id)
            ->whereNotNull('driver_started_at')
            ->where('driver_started_at', '<=', now())
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED']);
    }

    /**
     * @return array{
     *   success: bool,
     *   message: string,
     *   http_status?: int,
     *   data?: array<string, mixed>|null
     * }
     */
    public function startTripForDriver(Driver $driver, int $tripId): array
    {
        $trip = TripHistory::query()->find($tripId);
        if (! $trip) {
            return ['success' => false, 'message' => 'Trip not found.', 'http_status' => 404];
        }

        if ((int) ($trip->driver_id ?? 0) !== (int) $driver->id) {
            return ['success' => false, 'message' => 'forbidden', 'http_status' => 403];
        }

        if ((int) ($trip->driver_id ?? 0) <= 0) {
            return [
                'success' => false,
                'message' => __('dashboard.trip_no_driver_assigned'),
                'http_status' => 422,
            ];
        }

        $this->ensurePivotRows($trip);
        if (! $trip->tripHistoryStudents()->exists()) {
            return [
                'success' => false,
                'message' => __('dashboard.trip_cannot_start_without_students'),
                'http_status' => 422,
            ];
        }

        $st = strtoupper((string) ($trip->status ?? ''));
        if ($st === 'CANCELLED') {
            return ['success' => false, 'message' => 'Cannot start a cancelled trip.', 'http_status' => 422];
        }
        if ($st === 'COMPLETED') {
            return ['success' => false, 'message' => 'Trip is already completed.', 'http_status' => 422];
        }

        if ($trip->driver_started_at !== null) {
            $payload = $this->currentTripPayload($trip->fresh());

            return [
                'success' => true,
                'message' => 'Trip already started.',
                'data' => $payload,
            ];
        }

        if (! $this->canDriverStartTripNow($trip)) {
            [$windowStart] = $this->driverTripStartWindow($trip);
            if (now()->lt($windowStart)) {
                return [
                    'success' => false,
                    'message' => 'Trip is not available to start yet.',
                    'http_status' => 422,
                ];
            }

            return [
                'success' => false,
                'message' => 'Trip start window has ended.',
                'http_status' => 422,
            ];
        }

        $blocking = null;
        DB::transaction(function () use ($driver, $trip, $tripId, &$blocking): void {
            $blocking = TripHistory::query()
                ->where('driver_id', $driver->id)
                ->whereNotNull('driver_started_at')
                ->whereNotIn('status', ['CANCELLED', 'COMPLETED'])
                ->where('id', '!=', $tripId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($blocking instanceof TripHistory) {
                return;
            }

            $locked = TripHistory::query()->whereKey($trip->id)->lockForUpdate()->first();
            if ($locked === null || $locked->driver_started_at !== null) {
                return;
            }

            $locked->forceFill([
                'driver_started_at' => now(),
                'status' => 'ACTIVE',
            ])->save();
        });

        if ($blocking instanceof TripHistory) {
            return [
                'success' => false,
                'message' => 'Another trip is already in progress.',
                'http_status' => 422,
            ];
        }

        $trip->refresh();
        if ($trip->driver_started_at === null) {
            return ['success' => false, 'message' => 'Unable to start trip.', 'http_status' => 422];
        }

        $payload = $this->currentTripPayload($trip);

        return [
            'success' => true,
            'message' => 'تم بدء الرحلة بنجاح',
            'data' => $payload,
        ];
    }

    /**
     * @return array{success: bool, message: string, trip_id?: string, ended_at?: string}
     */
    public function endActiveTrip(Driver $driver): array
    {
        $trip = $this->activeTripForDriver($driver);
        if (! $trip) {
            return ['success' => false, 'message' => 'No active trip.'];
        }

        DB::transaction(function () use ($trip): void {
            $trip->forceFill([
                'end_time' => now(),
                'status' => 'COMPLETED',
            ])->save();
        });

        $trip->refresh();

        return [
            'success' => true,
            'message' => 'تم إنهاء الرحلة بنجاح',
            'trip_id' => $this->externalTripId($trip),
            'ended_at' => $trip->end_time instanceof Carbon
                ? $trip->end_time->toIso8601String()
                : null,
        ];
    }

    /**
     * @return array{success: bool, message: string, http_status?: int, trip_id?: string}
     */
    public function finalizeTripForDriver(
        Driver $driver,
        int $tripId,
        ?string $driverNotes,
        float $finalLat,
        float $finalLng,
        Carbon $endTimestamp,
    ): array {
        $trip = TripHistory::query()->find($tripId);
        if (! $trip) {
            return ['success' => false, 'message' => 'Trip not found.', 'http_status' => 404];
        }
        if ((int) ($trip->driver_id ?? 0) !== (int) $driver->id) {
            return ['success' => false, 'message' => 'forbidden', 'http_status' => 403];
        }
        if (strtoupper((string) $trip->status) === 'CANCELLED') {
            return ['success' => false, 'message' => 'Cannot finalize cancelled trip.', 'http_status' => 422];
        }

        DB::transaction(function () use ($trip, $driverNotes, $finalLat, $finalLng, $endTimestamp): void {
            $trip->forceFill([
                'note' => $driverNotes !== null && trim($driverNotes) !== '' ? trim($driverNotes) : $trip->note,
                'final_lat' => $finalLat,
                'final_lng' => $finalLng,
                'end_time' => $endTimestamp,
                'status' => 'COMPLETED',
            ])->save();
        });

        return [
            'success' => true,
            'message' => 'تم إغلاق الرحلة بنجاح، شكراً لك كابتن أحمد',
            'trip_id' => $this->externalTripId($trip),
        ];
    }

    /**
     * Trip detail screen for driver app + dashboard (scheduled trip card).
     *
     * @return array<string, mixed>
     */
    public function tripDetailPayload(TripHistory $trip): array
    {
        $trip->loadMissing('school');
        $this->ensurePivotRows($trip);
        $trip->load(['tripHistoryStudents.student']);

        $start = $trip->start_time instanceof Carbon ? $trip->start_time : Carbon::parse((string) $trip->start_time);

        $studentRows = $trip->tripHistoryStudents->sortBy(fn (TripHistoryStudent $ths): array => [$ths->sort_order, $ths->id]);
        $students = [];
        foreach ($studentRows as $ths) {
            $students[] = $this->tripDetailStudentRow($ths->student);
        }

        $count = max($studentRows->count(), (int) $trip->students_count);

        $referenceStudent = $this->firstReferenceStudentForTrip($trip);
        $school = $trip->school;
        $parentUser = $referenceStudent ? $this->primaryUserForGuardian((int) $referenceStudent->guardian_id) : null;

        $resolvedDistanceKm = ($school instanceof School && $referenceStudent instanceof Student)
            ? $this->transportDriverCardBuilder->resolveDistanceKmToSchool(
                null,
                null,
                $referenceStudent,
                $parentUser,
                $school,
            )
            : null;

        $distanceKmNumeric = $resolvedDistanceKm !== null
            ? (float) $resolvedDistanceKm
            : ($trip->distance_km !== null ? (float) $trip->distance_km : null);

        $routeNarrative = ($school instanceof School && $referenceStudent instanceof Student)
            ? $this->tripDetailRouteLocationNarrative($referenceStudent, $parentUser, $school)
            : null;
        $legacyLocation = $this->tripLocationLabel($trip);
        $locationOut = $routeNarrative ?? ($legacyLocation !== '' ? $legacyLocation : null);

        return [
            'id' => $this->externalTripId($trip),
            'title' => $this->tripDisplayTitle($trip),
            'students_number' => $count,
            'distance_km' => $distanceKmNumeric,
            'estimated_start_time' => $start->format('Y-m-d\TH:i:s'),
            'date_label' => $this->arabicTripDateLabel($start),
            'location' => $locationOut,
            'students' => $students,
        ];
    }

    private function firstReferenceStudentForTrip(TripHistory $trip): ?Student
    {
        $rows = $trip->tripHistoryStudents->sortBy(fn (TripHistoryStudent $ths): array => [$ths->sort_order, $ths->id]);
        foreach ($rows as $ths) {
            $student = $ths->student;
            if ($student instanceof Student && (int) ($student->guardian_id ?? 0) > 0) {
                return $student;
            }
        }

        return null;
    }

    private function primaryUserForGuardian(int $guardianId): ?User
    {
        if ($guardianId <= 0) {
            return null;
        }

        $users = User::query()
            ->where('guardian_id', $guardianId)
            ->with('homeLocation')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $home = $user->homeLocation;
            if ($home !== null && $home->latitude !== null && $home->longitude !== null) {
                return $user;
            }
        }

        return $users->first();
    }

    /**
     * Human-readable route aligned with {@see TransportDriverCardBuilder::resolveDistanceKmToSchool}:
     * student pickup → school when the student has coordinates; otherwise guardian home → school when home exists.
     */
    private function tripDetailRouteLocationNarrative(Student $student, ?User $parentUser, School $school): ?string
    {
        $schoolName = trim((string) ($school->name_ar ?: $school->name_en ?: ''));
        $ar = app()->getLocale() === 'ar';

        if ($student->latitude !== null && $student->longitude !== null) {
            $pickup = trim($this->studentAddressLine($student));
            $lead = $ar ? 'من نقطة الطالب إلى المدرسة' : 'Student pickup to school';
            if ($pickup !== '' && $schoolName !== '') {
                return $lead.': '.$pickup.' → '.$schoolName;
            }
            if ($schoolName !== '') {
                return $lead.': '.$schoolName;
            }

            return $pickup !== '' ? $lead.': '.$pickup : $lead;
        }

        if ($parentUser instanceof User) {
            $parentUser->loadMissing('homeLocation');
            $home = $parentUser->homeLocation;
            if ($home !== null && $home->latitude !== null && $home->longitude !== null) {
                $addr = is_string($home->formatted_address) ? trim($home->formatted_address) : '';
                $lead = $ar ? 'من منزل ولي الأمر إلى المدرسة' : 'Guardian home to school';
                if ($addr !== '' && $schoolName !== '') {
                    return $lead.': '.$addr.' → '.$schoolName;
                }
                if ($schoolName !== '') {
                    return $lead.': '.$schoolName;
                }
                if ($addr !== '') {
                    return $lead.': '.$addr;
                }

                return $lead;
            }
        }

        return null;
    }

    public function parseTripPublicId(string $raw): ?int
    {
        $raw = trim($raw);
        if (preg_match('/^TRP-(\d+)$/i', $raw, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * Backfill pivot rows from legacy students_preview JSON.
     */
    public function ensurePivotRows(TripHistory $trip): void
    {
        if ($trip->tripHistoryStudents()->exists()) {
            return;
        }

        $preview = $trip->students_preview;
        if (! is_array($preview)) {
            return;
        }

        $order = 0;
        foreach ($preview as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sid = (int) ($row['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            TripHistoryStudent::query()->firstOrCreate(
                [
                    'trip_history_id' => $trip->id,
                    'student_id' => $sid,
                ],
                [
                    'sort_order' => $order++,
                    'status' => StudentTripStopStatus::IDLE->value,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentTripPayload(?TripHistory $trip): ?array
    {
        if (! $trip) {
            return null;
        }

        $this->ensurePivotRows($trip);

        $trip->load(['tripHistoryStudents.student.school']);

        $rows = $trip->tripHistoryStudents;
        if ($rows->isEmpty()) {
            return [
                'trip_id' => $this->externalTripId($trip),
                'trip_type' => $trip->trip_type,
                'pending_students' => 0,
                'boarding_students' => 0,
                'current_active_student_id' => null,
                'students' => [],
            ];
        }

        $queueHeadId = $this->queueHeadStudentId($rows);

        $pending = 0;
        $boarding = 0;
        $studentsOut = [];

        foreach ($rows as $ths) {
            $st = StudentTripStopStatus::tryFrom((string) $ths->status) ?? StudentTripStopStatus::IDLE;
            if (in_array($st, [StudentTripStopStatus::IDLE, StudentTripStopStatus::ON_WAY, StudentTripStopStatus::ARRIVED], true)) {
                $pending++;
            }
            if ($st === StudentTripStopStatus::BOARDED) {
                $boarding++;
            }

            $student = $ths->student;
            $studentsOut[] = $this->studentCard($ths, $student, $queueHeadId);
        }

        return [
            'trip_id' => $this->externalTripId($trip),
            'trip_type' => $trip->trip_type,
            'pending_students' => $pending,
            'boarding_students' => $boarding,
            'current_active_student_id' => $queueHeadId !== null ? $this->externalStudentId($queueHeadId) : null,
            'students' => $studentsOut,
        ];
    }

    /**
     * @return array{success: bool, message: string, waiting_min?: int, less_than_50_meter?: bool}
     */
    public function applyStudentStatusChange(
        TripHistory $trip,
        Driver $driver,
        int $studentId,
        StudentTripStopStatus $newStatus,
        ?float $driverLat,
        ?float $driverLng,
    ): array {
        if ((int) $trip->driver_id !== (int) $driver->id) {
            return ['success' => false, 'message' => 'forbidden'];
        }

        $this->ensurePivotRows($trip);

        $trip->load('tripHistoryStudents');

        $ths = TripHistoryStudent::query()
            ->where('trip_history_id', $trip->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $ths) {
            return ['success' => false, 'message' => 'Student is not on this trip.'];
        }

        $queueHeadId = $this->queueHeadStudentId($trip->tripHistoryStudents);
        if ($queueHeadId === null || (int) $queueHeadId !== (int) $studentId) {
            return ['success' => false, 'message' => 'Another student is active in the pickup sequence.'];
        }

        $current = StudentTripStopStatus::tryFrom((string) $ths->status) ?? StudentTripStopStatus::IDLE;

        if (! $this->isValidTransition($current, $newStatus)) {
            return ['success' => false, 'message' => 'Invalid status transition.'];
        }

        $maxMeters = (int) config('trips.driver_arrival_max_distance_meters', 50);
        $waitingMin = (int) config('trips.default_waiting_minutes', 5);

        if ($newStatus === StudentTripStopStatus::ARRIVED) {
            $student = Student::query()->find($studentId);
            if (! $student || $student->latitude === null || $student->longitude === null) {
                return [
                    'success' => false,
                    'message' => 'Student pickup coordinates are missing.',
                    'waiting_min' => 0,
                    'less_than_50_meter' => false,
                ];
            }
            if ($driverLat === null || $driverLng === null) {
                return [
                    'success' => false,
                    'message' => 'driver_lat and driver_lng are required when marking ARRIVED.',
                    'waiting_min' => 0,
                    'less_than_50_meter' => false,
                ];
            }

            $meters = Haversine::metersBetween(
                (float) $driverLat,
                (float) $driverLng,
                (float) $student->latitude,
                (float) $student->longitude,
            );

            if ($meters > $maxMeters) {
                return [
                    'success' => false,
                    'message' => 'عذراً، أنت بعيد جداً عن موقع الطالب',
                    'waiting_min' => 0,
                    'less_than_50_meter' => false,
                ];
            }
        }

        DB::transaction(function () use ($ths, $newStatus): void {
            $ths->status = $newStatus->value;
            if ($newStatus === StudentTripStopStatus::ARRIVED) {
                $ths->arrived_at = now();
            }
            if ($newStatus === StudentTripStopStatus::BOARDED) {
                $ths->boarding_time = now();
            }
            $ths->save();
        });

        $lessThan50 = $newStatus === StudentTripStopStatus::ARRIVED;

        return [
            'success' => true,
            'message' => $newStatus === StudentTripStopStatus::ARRIVED
                ? 'تم تحديث الحالة بنجاح وبدأ وقت الانتظار'
                : 'تم تحديث الحالة بنجاح',
            'waiting_min' => $newStatus === StudentTripStopStatus::ARRIVED ? $waitingMin : 0,
            'less_than_50_meter' => $lessThan50,
        ];
    }

    /**
     * @param  Collection<int, TripHistoryStudent>  $rows
     */
    private function queueHeadStudentId($rows): ?int
    {
        foreach ($rows->sortBy(['sort_order', 'id']) as $ths) {
            $st = StudentTripStopStatus::tryFrom((string) $ths->status) ?? StudentTripStopStatus::IDLE;
            if (! in_array($st, [StudentTripStopStatus::BOARDED, StudentTripStopStatus::ABSENT], true)) {
                return (int) $ths->student_id;
            }
        }

        return null;
    }

    private function isValidTransition(StudentTripStopStatus $from, StudentTripStopStatus $to): bool
    {
        return match ($from) {
            StudentTripStopStatus::IDLE => $to === StudentTripStopStatus::ON_WAY,
            StudentTripStopStatus::ON_WAY => $to === StudentTripStopStatus::ARRIVED,
            StudentTripStopStatus::ARRIVED => in_array($to, [StudentTripStopStatus::BOARDED, StudentTripStopStatus::ABSENT], true),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function studentCard(TripHistoryStudent $ths, ?Student $student, ?int $queueHeadId): array
    {
        $st = StudentTripStopStatus::tryFrom((string) $ths->status) ?? StudentTripStopStatus::IDLE;

        $img = null;
        if ($student) {
            $resource = (new StudentResource($student))->toArray(request());
            $img = $resource['profilePhoto'] ?? null;
            if (is_string($img) && $img !== '' && ! str_starts_with($img, 'http')) {
                $img = url($img);
            }
        }

        $address = $this->studentAddressLine($student);

        return [
            'id' => $this->externalStudentId((int) $ths->student_id),
            'name' => $student?->full_name ?? '',
            'status' => $st->value,
            'img' => $img ?? '',
            'grade' => $student?->grade ?? '',
            'can_action' => $queueHeadId !== null && (int) $ths->student_id === (int) $queueHeadId,
            'location' => [
                'lat' => $student?->latitude !== null ? (float) $student->latitude : null,
                'lng' => $student?->longitude !== null ? (float) $student->longitude : null,
                'address' => $address,
            ],
            'boarding_time' => $this->formatBoardingTimeArabic($ths->boarding_time),
        ];
    }

    private function studentAddressLine(?Student $student): string
    {
        if (! $student) {
            return '';
        }
        $parts = array_filter([
            $student->district_area,
            $student->nearest_landmark,
        ], fn ($v): bool => is_string($v) && trim($v) !== '');

        return count($parts) > 0 ? implode(' - ', $parts) : '';
    }

    private function formatBoardingTimeArabic(?Carbon $t): ?string
    {
        if (! $t instanceof Carbon) {
            return null;
        }

        return $t->locale('ar')->translatedFormat('hh:mm a');
    }

    private function tripDisplayTitle(TripHistory $trip): string
    {
        if (is_string($trip->route_title) && trim($trip->route_title) !== '') {
            return trim($trip->route_title);
        }

        $school = $trip->school;
        $schoolName = $school?->name_ar ?? $school?->name_en ?? '';
        $typeAr = $this->tripTypeArabicLabel($trip->trip_type);

        return trim(($typeAr ?? 'رحلة').' - '.$schoolName);
    }

    private function tripTypeArabicLabel(?string $tripType): ?string
    {
        return match ($tripType) {
            TripType::MORNING_PICKUP->value => 'رحلة الصباح',
            TripType::MORNING_RETURN->value => 'رحلة العودة الصباحية',
            TripType::EVENING_PICKUP->value => 'رحلة المساء',
            TripType::EVENING_RETURN->value => 'رحلة العودة المسائية',
            default => null,
        };
    }

    private function tripLocationLabel(TripHistory $trip): string
    {
        $loc = trim((string) ($trip->location ?? ''));
        if ($loc !== '') {
            return $loc;
        }

        $school = $trip->school;
        if (! $school) {
            return '';
        }

        $district = trim((string) ($school->district ?? ''));
        $province = trim((string) ($school->province ?? ''));
        $address = trim((string) ($school->address ?? ''));

        if ($district !== '' && $province !== '') {
            return $district.'، '.$province;
        }

        if ($address !== '') {
            return $address;
        }

        return $district !== '' ? $district : $province;
    }

    private function formatDistanceKmArabic(float $km): string
    {
        $rounded = round($km, abs($km) >= 10 ? 0 : 1);
        $asFloat = (float) $rounded;
        $text = fmod($asFloat, 1.0) === 0.0 ? (string) (int) $rounded : (string) $rounded;

        return $text.' كم';
    }

    private function arabicTripDateLabel(Carbon $dt): string
    {
        $localized = $dt->copy()->locale('ar');
        if ($localized->isToday()) {
            return 'اليوم، '.$localized->translatedFormat('j F');
        }

        return $localized->translatedFormat('l، j F');
    }

    /**
     * @return array{id: string, name: string, image: string, grade: string, pickup_point: string}
     */
    private function tripDetailStudentRow(?Student $student): array
    {
        if (! $student) {
            return [
                'id' => '',
                'name' => '',
                'image' => '',
                'grade' => '',
                'pickup_point' => '',
            ];
        }

        $resource = (new StudentResource($student))->toArray(request());
        $img = $resource['profilePhoto'] ?? '';
        if (is_string($img) && $img !== '' && ! str_starts_with($img, 'http')) {
            $img = url($img);
        }

        return [
            'id' => $this->externalStudentId((int) $student->id),
            'name' => (string) ($student->full_name ?? ''),
            'image' => is_string($img) ? $img : '',
            'grade' => (string) ($student->grade ?? ''),
            'pickup_point' => $this->studentAddressLine($student),
        ];
    }

    public function externalTripId(TripHistory $trip): string
    {
        return 'TRP-'.$trip->id;
    }

    public function externalStudentId(int $studentId): string
    {
        return 'ST-'.str_pad((string) $studentId, 3, '0', STR_PAD_LEFT);
    }

    public function parseStudentPublicId(string $raw): ?int
    {
        $raw = trim($raw);
        if (preg_match('/^ST-(\d+)$/i', $raw, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw;
        }

        return null;
    }

    private function scheduledTripCardTitle(TripHistory $trip): string
    {
        $t = $trip->trip_type;
        if (is_string($t) && $t !== '') {
            return match ($t) {
                TripType::MORNING_PICKUP->value => 'رحلة الصباح - ذهاب',
                TripType::MORNING_RETURN->value => 'رحلة الصباح - عودة',
                TripType::EVENING_PICKUP->value => 'رحلة المساء - ذهاب',
                TripType::EVENING_RETURN->value => 'رحلة المساء - عودة',
                default => trim((string) ($trip->route_title ?? '')) ?: 'رحلة',
            };
        }

        return trim((string) ($trip->route_title ?? '')) ?: 'رحلة';
    }

    private function scheduledTripCardStatus(TripHistory $trip, Carbon $now): ScheduledTripCardStatus
    {
        $st = strtoupper((string) ($trip->status ?? ''));
        if ($st === 'CANCELLED') {
            return ScheduledTripCardStatus::CANCELLED;
        }
        if ($st === 'COMPLETED') {
            return ScheduledTripCardStatus::COMPLETED;
        }

        $end = $trip->end_time
            ? ($trip->end_time instanceof Carbon
                ? $trip->end_time->copy()
                : Carbon::parse((string) $trip->end_time))
            : null;

        if ($end !== null && $end->lt($now)) {
            return ScheduledTripCardStatus::COMPLETED;
        }

        if ($trip->driver_started_at !== null) {
            return ScheduledTripCardStatus::ONGOING;
        }

        [, $windowEnd] = $this->driverTripStartWindow($trip);
        if ($now->gt($windowEnd)) {
            return ScheduledTripCardStatus::COMPLETED;
        }

        return ScheduledTripCardStatus::UPCOMING;
    }

    private function scheduledTripTypeValue(TripHistory $trip): ?string
    {
        $t = $trip->trip_type;

        return is_string($t) && $t !== '' ? $t : null;
    }

    private function formatArabicShortTime(Carbon $dt): string
    {
        $h24 = (int) $dt->format('G');
        $suffix = $h24 < 12 ? 'ص' : 'م';

        return $dt->format('h:i').' '.$suffix;
    }
}
