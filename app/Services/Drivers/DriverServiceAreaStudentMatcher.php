<?php

namespace App\Services\Drivers;

use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Support\Geo\Haversine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Match a student's home location to a driver's Address Information (service areas).
 *
 * A student matches when their coordinates lie within the configured radius of at least
 * one sub-district (neighborhood) linked to any of the driver's service-area rows.
 */
final class DriverServiceAreaStudentMatcher
{
    public function studentMatchesDriverServiceAreas(Student $student, Driver $driver): bool
    {
        if (! $this->studentHasCoordinates($student)) {
            return false;
        }

        $driver->loadMissing(['serviceAreas.neighborhoods']);
        if ($driver->serviceAreas->isEmpty()) {
            return false;
        }

        $studentLat = (float) $student->latitude;
        $studentLng = (float) $student->longitude;
        $maxMeters = (float) config('routes.corridor_max_meters', 3000);

        foreach ($driver->serviceAreas as $serviceArea) {
            foreach ($serviceArea->neighborhoods as $neighborhood) {
                if ($neighborhood->latitude === null || $neighborhood->longitude === null) {
                    continue;
                }

                $meters = Haversine::metersBetween(
                    $studentLat,
                    $studentLng,
                    (float) $neighborhood->latitude,
                    (float) $neighborhood->longitude,
                );

                if ($meters <= $maxMeters) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function matchingDriverIdsForStudent(Student $student, School $school): array
    {
        if (! $this->studentHasCoordinates($student)) {
            return [];
        }

        return Driver::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereHas('serviceAreas.neighborhoods', function (Builder $query): void {
                $query->whereNotNull('latitude')->whereNotNull('longitude');
            })
            ->with(['serviceAreas.neighborhoods'])
            ->get()
            ->filter(fn (Driver $driver): bool => $this->studentMatchesDriverServiceAreas($student, $driver))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function studentHasCoordinates(Student $student): bool
    {
        return $student->latitude !== null
            && $student->longitude !== null
            && ! ((float) $student->latitude === 0.0 && (float) $student->longitude === 0.0);
    }
}
