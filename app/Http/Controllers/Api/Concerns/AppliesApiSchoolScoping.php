<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Sanctum users: admin sees all; other users are limited to schools derived from
 * {@see User::$school_id}, linked {@see Driver}, and (for parents)
 * {@see ParentContext::guardian()} — including every school their students attend.
 */
trait AppliesApiSchoolScoping
{
    protected function apiForbiddenResponse(string $msg = 'forbidden'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => (object) [],
            'msg' => $msg,
        ], 403);
    }

    protected function isApiAdmin(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Schools this user may access on the API.
     *
     * @return null Admin: no restriction (do not apply a school filter).
     * @return list<int> Non-admin: allowed school primary keys. Empty means no access.
     */
    protected function apiSchoolScopeSchoolIds(User $user): ?array
    {
        if ($this->isApiAdmin($user)) {
            return null;
        }

        $ids = collect();

        if ($user->school_id) {
            $ids->push((int) $user->school_id);
        }

        $user->loadMissing('driver');
        if ($user->driver?->school_id) {
            $ids->push((int) $user->driver->school_id);
        }

        $guardianIds = ParentContext::guardianIdsFor($user);
        if ($guardianIds !== []) {
            $guardianSchools = Guardian::query()
                ->whereIn('id', $guardianIds)
                ->pluck('school_id');

            foreach ($guardianSchools as $sid) {
                if ($sid !== null) {
                    $ids->push((int) $sid);
                }
            }

            $studentSchools = Student::query()
                ->whereIn('guardian_id', $guardianIds)
                ->pluck('school_id');

            foreach ($studentSchools as $sid) {
                if ($sid !== null) {
                    $ids->push((int) $sid);
                }
            }
        }

        return $ids->filter(fn (int $id): bool => $id > 0)->unique()->values()->all();
    }

    protected function applyApiScopeBySchoolIdColumn(Builder $query, User $user, string $column = 'school_id'): void
    {
        if ($this->isApiAdmin($user)) {
            return;
        }
        $ids = $this->apiSchoolScopeSchoolIds($user);
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereIn($column, $ids);
    }

    /** For the schools table, non-admins only see rows in their scope. */
    protected function applyApiScopeToSchoolsQuery(Builder $query, User $user): void
    {
        if ($this->isApiAdmin($user)) {
            return;
        }
        $ids = $this->apiSchoolScopeSchoolIds($user);
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereIn('id', $ids);
    }

    /**
     * Reject any non-admin (e.g. schools CRUD, trip org mutations).
     */
    protected function ensureApiAdminForMutations(User $user): ?JsonResponse
    {
        if (! $this->isApiAdmin($user)) {
            return $this->apiForbiddenResponse('forbidden');
        }

        return null;
    }

    /**
     * Global admin or school-linked user ({@see User::$school_id}) may create/update/delete
     * drivers, guardians, and students for that school only.
     */
    protected function ensureApiCanMutateSchoolRoster(User $user, int $targetSchoolId): ?JsonResponse
    {
        if ($this->isApiAdmin($user)) {
            return null;
        }

        if (! $user->school_id) {
            return $this->apiForbiddenResponse('forbidden');
        }

        return $this->ensureApiTargetsOwnSchoolOrAdmin($user, $targetSchoolId);
    }

    /**
     * Reject non-admins with no school scope, or if the target school is not in their allowed set.
     */
    protected function ensureApiTargetsOwnSchoolOrAdmin(User $user, ?int $targetSchoolId): ?JsonResponse
    {
        if ($this->isApiAdmin($user)) {
            return null;
        }
        $scopes = $this->apiSchoolScopeSchoolIds($user);
        if ($scopes === null) {
            return null;
        }
        if ($scopes === []) {
            return $this->apiForbiddenResponse('A school must be linked to this account to perform this action.');
        }
        if ($targetSchoolId === null) {
            return $this->apiForbiddenResponse('forbidden');
        }
        if (! in_array((int) $targetSchoolId, $scopes, true)) {
            return $this->apiForbiddenResponse('forbidden');
        }

        return null;
    }

    /** View/edit a single school row. */
    protected function ensureApiCanAccessSchoolId(User $user, int $schoolId): ?JsonResponse
    {
        return $this->ensureApiTargetsOwnSchoolOrAdmin($user, $schoolId);
    }
}
