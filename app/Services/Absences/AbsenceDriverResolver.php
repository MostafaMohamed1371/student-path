<?php

namespace App\Services\Absences;

use App\Models\Driver;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;

/**
 * Resolve the subscribed transport driver for a student (parent route assignment).
 */
final class AbsenceDriverResolver
{
    /**
     * @return array{driver: Driver|null, transport_route: TransportRoute|null}
     */
    public function resolveForStudent(Student $student): array
    {
        $student->loadMissing('transportRouteStudent.transportRoute.driver');

        $assignment = $student->transportRouteStudent;
        if (! $assignment instanceof TransportRouteStudent) {
            return ['driver' => null, 'transport_route' => null];
        }

        $route = $assignment->transportRoute;
        if (! $route instanceof TransportRoute || $route->status !== 'active') {
            return ['driver' => null, 'transport_route' => null];
        }

        $driver = $route->driver;
        if (! $driver instanceof Driver || $driver->status !== 'active') {
            return ['driver' => null, 'transport_route' => $route];
        }

        if ((int) $driver->school_id !== (int) $student->school_id) {
            return ['driver' => null, 'transport_route' => $route];
        }

        return ['driver' => $driver, 'transport_route' => $route];
    }
}
