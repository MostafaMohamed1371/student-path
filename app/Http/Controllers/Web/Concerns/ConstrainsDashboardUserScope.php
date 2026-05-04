<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Same visibility rules as wallet / in-app notification reports: admins see all;
 * staff see users tied to their {@see User::scopingSchoolId()} school.
 */
trait ConstrainsDashboardUserScope
{
    /**
     * @param  Builder<User>  $query
     */
    protected function constrainUsersToDashboardScope(Builder $query): void
    {
        $user = auth()->user();
        if (! $user || $user->is_admin) {
            return;
        }
        $sid = $user->scopingSchoolId();
        if ($sid === null) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where(function (Builder $q) use ($sid): void {
            $q->where('school_id', $sid)
                ->orWhereHas('guardian', fn (Builder $g) => $g->where('school_id', $sid))
                ->orWhereHas('driver', fn (Builder $d) => $d->where('school_id', $sid));
        });
    }

    protected function userIdVisibleInDashboardScope(int $userId): bool
    {
        $auth = auth()->user();
        if (! $auth) {
            return false;
        }
        if ($auth->is_admin) {
            return true;
        }

        return User::query()
            ->whereKey($userId)
            ->tap(fn (Builder $q) => $this->constrainUsersToDashboardScope($q))
            ->exists();
    }
}
