<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Sanctum users: admin sees all; other users are limited to the school on their
 * account or (for drivers) on their linked {@see \App\Models\Driver} record.
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
     * Non-admin: school_id from the user, else from the linked driver. Null = no access to scoped data.
     */
    protected function effectiveApiSchoolId(User $user): ?int
    {
        if ($this->isApiAdmin($user)) {
            return null;
        }
        if ($user->school_id) {
            return (int) $user->school_id;
        }
        $user->loadMissing('driver');
        if ($user->driver?->school_id) {
            return (int) $user->driver->school_id;
        }

        return null;
    }

    protected function applyApiScopeBySchoolIdColumn(Builder $query, User $user, string $column = 'school_id'): void
    {
        if ($this->isApiAdmin($user)) {
            return;
        }
        $id = $this->effectiveApiSchoolId($user);
        if ($id === null) {
            $query->whereRaw('0 = 1');
            return;
        }
        $query->where($column, $id);
    }

    /** For the schools table, non-admins only see the row whose primary key matches their scope. */
    protected function applyApiScopeToSchoolsQuery(Builder $query, User $user): void
    {
        if ($this->isApiAdmin($user)) {
            return;
        }
        $id = $this->effectiveApiSchoolId($user);
        if ($id === null) {
            $query->whereRaw('0 = 1');
            return;
        }
        $query->where('id', $id);
    }

    /**
     * Reject any non-admin. Use for create/update/destroy to match dashboard: only {@see User::is_admin}
     * may mutate schools, students, guardians, and drivers.
     */
    protected function ensureApiAdminForMutations(User $user): ?JsonResponse
    {
        if (! $this->isApiAdmin($user)) {
            return $this->apiForbiddenResponse('forbidden');
        }

        return null;
    }

    /**
     * Reject non-admins with no school scope, or if the target school is not their school.
     */
    protected function ensureApiTargetsOwnSchoolOrAdmin(User $user, ?int $targetSchoolId): ?JsonResponse
    {
        if ($this->isApiAdmin($user)) {
            return null;
        }
        $scope = $this->effectiveApiSchoolId($user);
        if ($scope === null) {
            return $this->apiForbiddenResponse('A school must be linked to this account to perform this action.');
        }
        if ((int) $targetSchoolId !== (int) $scope) {
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
