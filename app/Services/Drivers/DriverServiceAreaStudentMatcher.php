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

        return $this->coordinatesMatchDriverServiceAreas(
            (float) $student->latitude,
            (float) $student->longitude,
            $driver,
        );
    }

    public function coordinatesMatchDriverServiceAreas(float $latitude, float $longitude, Driver $driver): bool
    {
        $driver->loadMissing(['serviceAreas.neighborhoods']);
        if ($driver->serviceAreas->isEmpty()) {
            return false;
        }

        $maxMeters = (float) config('routes.corridor_max_meters', 3000);

        foreach ($driver->serviceAreas as $serviceArea) {
            foreach ($serviceArea->neighborhoods as $neighborhood) {
                if ($neighborhood->latitude === null || $neighborhood->longitude === null) {
                    continue;
                }

                $meters = Haversine::metersBetween(
                    $latitude,
                    $longitude,
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

        return $this->matchingDriverIdsForCoordinates(
            (float) $student->latitude,
            (float) $student->longitude,
            $school,
        );
    }

    /**
     * @return list<int>
     */
    public function matchingDriverIdsForCoordinates(float $latitude, float $longitude, School $school): array
    {
        return Driver::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereHas('serviceAreas.neighborhoods', function (Builder $query): void {
                $query->whereNotNull('latitude')->whereNotNull('longitude');
            })
            ->with(['serviceAreas.neighborhoods'])
            ->get()
            ->filter(fn (Driver $driver): bool => $this->coordinatesMatchDriverServiceAreas($latitude, $longitude, $driver))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Drivers whose service areas include the parent's pickup sub-district.
     *
     * @return list<int>
     */
    public function matchingDriverIdsForNeighborhood(int $neighborhoodId, int $schoolId): array
    {
        if ($neighborhoodId <= 0 || $schoolId <= 0) {
            return [];
        }

        return Driver::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereHas('serviceAreas.neighborhoods', fn (Builder $query): Builder => $query->whereKey($neighborhoodId))
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
