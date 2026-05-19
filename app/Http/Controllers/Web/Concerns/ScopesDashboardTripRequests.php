<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Driver;
use App\Models\TripRequest;
use Illuminate\Database\Eloquent\Builder;

trait ScopesDashboardTripRequests
{
    protected function tripRequestListQuery(): Builder
    {
        return TripRequest::query()
            ->with(['user.guardian', 'student.guardian', 'student.school', 'driver.school', 'tripHistory']);
    }

    /**
     * Admin: all trip_requests (optional school/driver filters via request query).
     * School staff: only rows tied to their scoping school.
     * Driver: only rows assigned to their driver id.
     */
    protected function applyTripRequestDashboardScope(Builder $query, ?int $filterSchoolId = null, ?int $filterDriverId = null): void
    {
        $auth = auth()->user();
        if (! $auth) {
            $query->whereRaw('0 = 1');

            return;
        }

        // Admins always see the full trip_requests list (optional school/driver filters only).
        if ($auth->is_admin) {
            if ($filterSchoolId !== null && $filterSchoolId > 0) {
                $this->constrainTripRequestsToSchool($query, $filterSchoolId);
            }
        } elseif (($driver = $this->currentDriver()) instanceof Driver) {
            $query->where('driver_id', $driver->id);
        } else {
            $sid = $auth->scopingSchoolId();
            if ($sid === null) {
                $query->whereRaw('0 = 1');
            } else {
                $this->constrainTripRequestsToSchool($query, (int) $sid);
            }
        }

        if ($filterDriverId !== null && $filterDriverId > 0) {
            $query->where('driver_id', $filterDriverId);
        }
    }

    /**
     * Match trip requests linked to a school via student, driver, parent user, or guardian.
     */
    protected function constrainTripRequestsToSchool(Builder $query, int $schoolId): void
    {
        $query->forDashboardSchool($schoolId);
    }

    abstract protected function currentDriver(): ?Driver;
}
