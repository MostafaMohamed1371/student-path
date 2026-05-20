<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Enums\TripType;
use App\Models\Driver;
use App\Models\TransportRouteStudent;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripRequest;
use App\Services\Trips\DriverShiftResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ProvidesDashboardSchoolDriverFilters
{
    use ManagesDashboardScoping;

    /**
     * @return array{
     *     schools: Collection<int, School>,
     *     drivers: Collection<int, Driver>,
     *     filterSchoolId: int,
     *     filterDriverId: int,
     *     effectiveSchoolId: int,
     *     restrictEmpty: bool,
     *     showSchoolFilter: bool,
     *     showDriverFilter: bool,
     *     showShiftFilter: bool,
     *     filterShiftPeriod: string,
     *     showStudentFilter: bool,
     *     filterStudentId: int,
     *     students: Collection<int, Student>,
     *     showUserRoleFilter: bool,
     *     filterUserRole: string,
     *     showGuardianFilter: bool,
     *     filterGuardianId: int,
     *     guardians: Collection<int, Guardian>,
     *     showTripTypeFilter: bool,
     *     filterTripType: string,
     *     tripTypeOptions: list<string>
     * }
     */
    protected function dashboardReportFilterContext(
        Request $request,
        bool $withShiftFilter = false,
        bool $withStudentFilter = false,
        bool $withUserRoleFilter = false,
        bool $withGuardianFilter = false,
        bool $withTripTypeFilter = false,
    ): array {
        $auth = auth()->user();
        $filterSchoolId = 0;
        $filterDriverId = (int) $request->query('driver_id', 0);
        $filterStudentId = 0;
        $filterGuardianId = 0;
        $filterUserRole = '';
        $filterShiftPeriod = '';
        $restrictEmpty = false;

        if ($withShiftFilter) {
            $rawShift = strtoupper(trim((string) $request->query('shift_period', '')));
            if (in_array($rawShift, [DriverShiftResolver::MORNING, DriverShiftResolver::EVENING, 'BOTH'], true)) {
                $filterShiftPeriod = $rawShift;
            }
        }

        if ((bool) $auth?->is_admin) {
            $filterSchoolId = (int) $request->query('school_id', 0);
            if ($filterSchoolId > 0) {
                abort_unless(School::query()->whereKey($filterSchoolId)->exists(), 404);
            }
        } elseif ($auth) {
            $scoped = $auth->scopingSchoolId();
            if ($scoped === null) {
                $restrictEmpty = true;
            } else {
                $filterSchoolId = (int) $scoped;
            }
        } else {
            $restrictEmpty = true;
        }

        if ($filterDriverId > 0 && ! $this->driverIdAllowedForReportFilter($filterDriverId, $filterSchoolId)) {
            $filterDriverId = 0;
        }

        if ($withStudentFilter) {
            $filterStudentId = (int) $request->query('student_id', 0);
            if ($filterStudentId > 0 && ! $this->studentIdAllowedForReportFilter($filterStudentId, $filterSchoolId)) {
                $filterStudentId = 0;
            }
        }

        if ($withGuardianFilter) {
            $filterGuardianId = (int) $request->query('guardian_id', 0);
            if ($filterGuardianId > 0 && ! $this->guardianIdAllowedForReportFilter($filterGuardianId, $filterSchoolId)) {
                $filterGuardianId = 0;
            }
        }

        if ($withUserRoleFilter) {
            $rawRole = strtolower(trim((string) $request->query('user_role', '')));
            if (in_array($rawRole, ['admin', 'driver', 'guardian', 'student'], true)) {
                $filterUserRole = $rawRole;
            }
        }

        $filterTripType = '';
        $tripTypeOptions = [];
        if ($withTripTypeFilter) {
            $tripTypeOptions = collect(TripType::cases())
                ->map(fn (TripType $t): string => $t->value)
                ->values()
                ->all();
            $rawTripType = trim((string) $request->query('trip_type', ''));
            if ($rawTripType !== '' && in_array($rawTripType, $tripTypeOptions, true)) {
                $filterTripType = $rawTripType;
            }
        }

        $effectiveSchoolId = (bool) $auth?->is_admin
            ? $filterSchoolId
            : ($restrictEmpty ? 0 : $filterSchoolId);

        return [
            'schools' => $this->schoolsForRosterForm(),
            'drivers' => $this->driversForReportFilter($effectiveSchoolId > 0 ? $effectiveSchoolId : null),
            'filterSchoolId' => $filterSchoolId,
            'filterDriverId' => $filterDriverId,
            'effectiveSchoolId' => $effectiveSchoolId,
            'restrictEmpty' => $restrictEmpty,
            'showSchoolFilter' => (bool) $auth?->is_admin,
            'showDriverFilter' => $auth !== null,
            'showShiftFilter' => $withShiftFilter,
            'filterShiftPeriod' => $filterShiftPeriod,
            'showStudentFilter' => $withStudentFilter,
            'filterStudentId' => $filterStudentId,
            'students' => $withStudentFilter
                ? $this->studentsForReportFilter($effectiveSchoolId > 0 ? $effectiveSchoolId : null)
                : collect(),
            'showUserRoleFilter' => $withUserRoleFilter,
            'filterUserRole' => $filterUserRole,
            'showGuardianFilter' => $withGuardianFilter,
            'filterGuardianId' => $filterGuardianId,
            'guardians' => $withGuardianFilter
                ? $this->guardiansForReportFilter($effectiveSchoolId > 0 ? $effectiveSchoolId : null)
                : collect(),
            'showTripTypeFilter' => $withTripTypeFilter,
            'filterTripType' => $filterTripType,
            'tripTypeOptions' => $tripTypeOptions,
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyRosterShiftFilter(Builder $query, array $filters): void
    {
        if ($filters['restrictEmpty'] ?? false) {
            return;
        }

        $shift = (string) ($filters['filterShiftPeriod'] ?? '');
        if ($shift === '') {
            return;
        }

        if ($shift === 'BOTH') {
            $query->where('shift_period', 'BOTH');

            return;
        }

        $query->where(function (Builder $q) use ($shift): void {
            $q->whereNull('shift_period')
                ->orWhere('shift_period', $shift)
                ->orWhere('shift_period', 'BOTH');
        });
    }

    /**
     * @param  Builder<\App\Models\TripHistory>  $query
     */
    protected function applyTripHistoryShiftFilter(Builder $query, array $filters): void
    {
        if ($filters['restrictEmpty'] ?? false) {
            return;
        }

        $shift = (string) ($filters['filterShiftPeriod'] ?? '');
        if ($shift === '') {
            return;
        }

        if ($shift === 'BOTH') {
            $query->whereHas('driver', fn (Builder $d) => $d->where('shift_period', 'BOTH'));

            return;
        }

        $tripTypes = match ($shift) {
            DriverShiftResolver::MORNING => [
                TripType::MORNING_PICKUP->value,
                TripType::MORNING_RETURN->value,
            ],
            DriverShiftResolver::EVENING => [
                TripType::EVENING_PICKUP->value,
                TripType::EVENING_RETURN->value,
            ],
            default => [],
        };

        if ($tripTypes === []) {
            return;
        }

        $query->where(function (Builder $q) use ($shift, $tripTypes): void {
            $q->whereIn('trip_type', $tripTypes)
                ->orWhere(function (Builder $q2) use ($shift): void {
                    $q2->whereNull('trip_type')
                        ->whereHas('driver', function (Builder $d) use ($shift): void {
                            $d->where(function (Builder $d2) use ($shift): void {
                                $d2->whereNull('shift_period')
                                    ->orWhere('shift_period', $shift)
                                    ->orWhere('shift_period', 'BOTH');
                            });
                        });
                });
        });
    }

    protected function applyDashboardReportFilters(Builder $query, array $filters, string $scope): void
    {
        if ($filters['restrictEmpty']) {
            $query->whereRaw('0 = 1');

            return;
        }

        $schoolId = (int) $filters['effectiveSchoolId'];
        $driverId = (int) $filters['filterDriverId'];

        if ($scope === 'bus_list') {
            if ($schoolId > 0) {
                $query->whereHas('driver', fn (Builder $d) => $d->where('school_id', $schoolId));
            }
            if ($driverId > 0) {
                $query->where('driver_id', $driverId);
            }

            return;
        }

        if ($scope === 'delay_sos_alert') {
            if ($schoolId > 0) {
                $query->whereHas('tripHistory', fn (Builder $q) => $q->where('school_id', $schoolId));
            }
            if ($driverId > 0) {
                $query->where(function (Builder $q) use ($driverId): void {
                    $q->where('driver_id', $driverId)
                        ->orWhereHas('tripHistory', fn (Builder $t) => $t->where('driver_id', $driverId));
                });
            }

            return;
        }

        if ($schoolId > 0) {
            match ($scope) {
                'trip_history' => $query->where('school_id', $schoolId),
                'trip_history_relation' => $query->whereHas(
                    'tripHistory',
                    fn (Builder $q) => $q->where('school_id', $schoolId),
                ),
                'student_relation' => $query->whereHas(
                    'student',
                    fn (Builder $q) => $q->where('school_id', $schoolId),
                ),
                'user_relation' => $query->whereHas(
                    'user',
                    fn (Builder $q) => $this->constrainUsersToReportSchool($q, $schoolId),
                ),
                'user_school' => $this->constrainUsersToReportSchool($query, $schoolId),
                'trip_request' => $query->forDashboardSchool($schoolId),
                'roster_school' => $query->where('school_id', $schoolId),
                'school_row' => $query->whereKey($schoolId),
                default => null,
            };
        }

        if ($driverId > 0) {
            match ($scope) {
                'trip_history', 'direct_driver' => $query->where('driver_id', $driverId),
                'trip_history_relation' => $query->whereHas(
                    'tripHistory',
                    fn (Builder $q) => $q->where('driver_id', $driverId),
                ),
                'user_driver' => $query->whereHas(
                    'user',
                    fn (Builder $q) => $q->whereHas('driver', fn (Builder $d) => $d->whereKey($driverId)),
                ),
                'user_driver_direct' => $query->whereHas(
                    'driver',
                    fn (Builder $d) => $d->whereKey($driverId),
                ),
                'route_driver' => $query->where('driver_id', $driverId),
                'driver_roster' => $query->whereKey($driverId),
                'student_driver_route' => $query->whereHas(
                    'transportRouteStudent.transportRoute',
                    fn (Builder $q) => $q->where('driver_id', $driverId),
                ),
                'guardian_driver_route' => $query->whereHas(
                    'students',
                    fn (Builder $q) => $q->whereHas(
                        'transportRouteStudent.transportRoute',
                        fn (Builder $r) => $r->where('driver_id', $driverId),
                    ),
                ),
                'school_driver' => $query->whereHas(
                    'drivers',
                    fn (Builder $q) => $q->whereKey($driverId),
                ),
                default => null,
            };
        }

        $studentId = (int) ($filters['filterStudentId'] ?? 0);
        if ($studentId > 0) {
            match ($scope) {
                'school_student' => $query->whereHas(
                    'students',
                    fn (Builder $q) => $q->whereKey($studentId),
                ),
                'direct_student' => $query->where('student_id', $studentId),
                'user_student' => $this->constrainUsersToStudent($query, $studentId),
                'route_student' => $query->whereHas(
                    'routeStudents',
                    fn (Builder $q) => $q->where('student_id', $studentId),
                ),
                default => null,
            };
        }

        $guardianId = (int) ($filters['filterGuardianId'] ?? 0);
        if ($guardianId > 0) {
            match ($scope) {
                'user_guardian' => $this->constrainUsersToGuardian($query, $guardianId),
                default => null,
            };
        }
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    protected function applyDashboardUserRoleFilter(Builder $query, array $filters): void
    {
        if ($filters['restrictEmpty'] ?? false) {
            return;
        }

        $role = (string) ($filters['filterUserRole'] ?? '');
        if ($role === '') {
            return;
        }

        match ($role) {
            'admin' => $query->where('is_admin', true),
            'driver' => $query->whereHas('driver'),
            'guardian' => $query->where(function (Builder $q): void {
                $q->whereNotNull('guardian_id')
                    ->orWhereHas('guardian')
                    ->orWhereIn('phone', Guardian::query()->select('phone'))
                    ->orWhereExists(
                        Guardian::query()
                            ->selectRaw('1')
                            ->whereRaw("users.phone = CONCAT('964', guardians.phone)")
                            ->whereRaw('LENGTH(guardians.phone) = 10'),
                    );
            }),
            'student' => $query->where(function (Builder $q): void {
                $q->whereExists(
                    Student::query()
                        ->selectRaw('1')
                        ->whereColumn('students.student_phone', 'users.phone'),
                )->orWhereExists(
                    Student::query()
                        ->selectRaw('1')
                        ->whereRaw("users.phone = CONCAT('964', students.student_phone)")
                        ->whereRaw('LENGTH(students.student_phone) = 10'),
                );
            }),
            default => null,
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    /**
     * @param  Builder<\App\Models\TransportRoute>  $query
     */
    protected function applyTransportRouteTripTypeFilter(Builder $query, array $filters): void
    {
        if ($filters['restrictEmpty'] ?? false) {
            return;
        }

        $tripType = (string) ($filters['filterTripType'] ?? '');
        if ($tripType === '') {
            return;
        }

        $query->where('trip_type', $tripType);
    }

    protected function applyStudentRelationShiftFilter(Builder $query, array $filters, string $studentRelation = 'student'): void
    {
        if ($filters['restrictEmpty'] ?? false) {
            return;
        }

        if (($filters['filterShiftPeriod'] ?? '') === '') {
            return;
        }

        $query->whereHas($studentRelation, function (Builder $q) use ($filters): void {
            $this->applyRosterShiftFilter($q, $filters);
        });
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    protected function constrainUsersToReportSchool(Builder $query, int $schoolId): void
    {
        $query->where(function (Builder $q) use ($schoolId): void {
            $q->where('school_id', $schoolId)
                ->orWhereHas('guardian', fn (Builder $g) => $g->where('school_id', $schoolId))
                ->orWhereHas('driver', fn (Builder $d) => $d->where('school_id', $schoolId));
        });
    }

    /** @return Collection<int, Driver> */
    protected function driversForReportFilter(?int $schoolId): Collection
    {
        $query = Driver::query()
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($schoolId !== null && $schoolId > 0) {
            $query->where('school_id', $schoolId);
        } elseif (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($query);
        }

        return $query->get();
    }

    protected function driverIdAllowedForReportFilter(int $driverId, int $filterSchoolId): bool
    {
        $query = Driver::query()->whereKey($driverId);

        if ($filterSchoolId > 0) {
            $query->where('school_id', $filterSchoolId);
        } elseif (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($query);
        }

        return $query->exists();
    }

    /** @return Collection<int, Student> */
    protected function studentsForReportFilter(?int $schoolId): Collection
    {
        $query = Student::query()
            ->orderBy('full_name')
            ->orderBy('id');

        if ($schoolId !== null && $schoolId > 0) {
            $query->where('school_id', $schoolId);
        } elseif (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($query);
        }

        return $query->get(['id', 'full_name', 'grade', 'school_id']);
    }

    protected function studentIdAllowedForReportFilter(int $studentId, int $filterSchoolId): bool
    {
        $query = Student::query()->whereKey($studentId);

        if ($filterSchoolId > 0) {
            $query->where('school_id', $filterSchoolId);
        } elseif (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($query);
        }

        return $query->exists();
    }

    /** @return Collection<int, Guardian> */
    protected function guardiansForReportFilter(?int $schoolId): Collection
    {
        $query = Guardian::query()
            ->orderBy('full_name')
            ->orderBy('id');

        if ($schoolId !== null && $schoolId > 0) {
            $query->where('school_id', $schoolId);
        } elseif (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($query);
        }

        return $query->get(['id', 'full_name', 'phone', 'school_id']);
    }

    protected function guardianIdAllowedForReportFilter(int $guardianId, int $filterSchoolId): bool
    {
        $query = Guardian::query()->whereKey($guardianId);

        if ($filterSchoolId > 0) {
            $query->where('school_id', $filterSchoolId);
        } elseif (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($query);
        }

        return $query->exists();
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    protected function constrainUsersToGuardian(Builder $query, int $guardianId): void
    {
        $guardian = Guardian::query()->whereKey($guardianId)->first();
        if ($guardian === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $phones = array_values(array_unique(array_filter([
            $guardian->phone,
            is_string($guardian->phone) && strlen($guardian->phone) === 10
                ? '964'.$guardian->phone
                : null,
        ])));

        $query->where(function (Builder $q) use ($guardian, $phones): void {
            $q->where('guardian_id', $guardian->id);
            if ($phones !== []) {
                $q->orWhereIn('phone', $phones);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    protected function constrainUsersToStudent(Builder $query, int $studentId): void
    {
        $student = Student::query()->with('guardian')->whereKey($studentId)->first();
        if ($student === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $phones = array_values(array_unique(array_filter([
            $student->student_phone,
            is_string($student->student_phone) && strlen($student->student_phone) === 10
                ? '964'.$student->student_phone
                : null,
            $student->guardian?->phone,
            is_string($student->guardian?->phone) && strlen((string) $student->guardian->phone) === 10
                ? '964'.$student->guardian->phone
                : null,
        ])));

        $query->where(function (Builder $q) use ($student, $phones): void {
            if ($student->guardian_id) {
                $q->where('guardian_id', $student->guardian_id);
            }
            if ($phones !== []) {
                $q->orWhereIn('phone', $phones);
            }
            $q->orWhereHas('tripRequests', fn (Builder $t) => $t->where('student_id', $student->id));
        });
    }
}
