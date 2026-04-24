<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

/**
 * Non-admins: scope data to {@see \App\Models\User::scopingSchoolId()}.
 * When that is null, queries must return no rows (not `whereNull` by mistake).
 */
trait ManagesDashboardScoping
{
    use RedirectsWhenNoSchoolForStaff;

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
        $query->whereHas('driver', fn (Builder $q) => $q->where('school_id', $id));
    }
}
