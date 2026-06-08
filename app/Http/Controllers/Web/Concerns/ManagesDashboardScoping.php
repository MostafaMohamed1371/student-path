<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Guardian;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Non-admins: scope data to {@see \App\Models\User::scopingSchoolId()}.
 * When that is null, queries must return no rows (not `whereNull` by mistake).
 */
trait ManagesDashboardScoping
{
    use RedirectsWhenNoSchoolForStaff;

    protected function dashboardListPerPage(): int
    {
        return 25;
    }

    /**
     * @param  'school_id'|string  $column  Foreign key column on the root model (e.g. drivers.school_id).
     */
    protected function constrainToScopingSchool(Builder $query, string $column = 'school_id'): void
    {
        if ((bool) auth()->user()?->is_admin) {
            return;
        }
        $id = auth()->user()?->scopingSchoolId();
        if ($id === null) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where($column, $id);
    }

    /**
     * For queries on the `schools` table: non-admins only see the row with id = their scoping school.
     */
    protected function constrainToScopingSchoolRow(Builder $query): void
    {
        if ((bool) auth()->user()?->is_admin) {
            return;
        }
        $id = auth()->user()?->scopingSchoolId();
        if ($id === null) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where('id', $id);
    }

    /**
     * Buses are linked to drivers; non-admins only see buses whose driver belongs to their scoping school.
     */
    protected function constrainBusesToScopingDriverSchool(Builder $query): void
    {
        if ((bool) auth()->user()?->is_admin) {
            return;
        }
        $id = auth()->user()?->scopingSchoolId();
        if ($id === null) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where(function (Builder $q) use ($id): void {
            $q->where('school_id', $id)
                ->orWhereHas('driver', fn (Builder $d) => $d->where('school_id', $id));
        });
    }

    /** Global admin or user with {@see User::$school_id} (school staff). */
    protected function canMutateSchoolRoster(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return (bool) $user->is_admin || $user->school_id !== null;
    }

    protected function abortUnlessCanMutateSchoolRoster(): void
    {
        abort_unless($this->canMutateSchoolRoster(), 403);
    }

    protected function abortUnlessCanMutateSchoolRosterForSchool(int $schoolId): void
    {
        $this->abortUnlessCanMutateSchoolRoster();
        if ((bool) auth()->user()?->is_admin) {
            return;
        }
        $sid = auth()->user()?->scopingSchoolId();
        abort_unless($sid !== null && (int) $sid === $schoolId, 403);
    }

    /**
     * Force non-admin creates to the user's scoping school.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function enforceRosterSchoolIdForStaff(array $validated): array
    {
        if ((bool) auth()->user()?->is_admin) {
            return $validated;
        }
        $sid = auth()->user()?->scopingSchoolId();
        if ($sid !== null) {
            $validated['school_id'] = $sid;
        }

        return $validated;
    }

    /** @return Collection<int, School> */
    protected function schoolsForRosterForm(): Collection
    {
        if ((bool) auth()->user()?->is_admin) {
            return School::query()->orderBy('name_en')->get();
        }
        $sid = auth()->user()?->scopingSchoolId();
        if ($sid === null) {
            return collect();
        }

        return School::query()->where('id', $sid)->orderBy('name_en')->get();
    }

    /** @return Collection<int, Guardian> */
    protected function guardiansForRosterForm(): Collection
    {
        $q = Guardian::query()->orderBy('full_name');
        if (! (bool) auth()->user()?->is_admin) {
            $this->constrainToScopingSchool($q);
        }

        return $q->get();
    }
}
