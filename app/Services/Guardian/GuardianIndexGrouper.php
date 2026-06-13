<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Support\GuardianIdentityKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

final class GuardianIndexGrouper
{
    /**
     * @param  Collection<int, Guardian>  $guardians
     * @return Collection<int, GuardianIndexGroup>
     */
    public function group(Collection $guardians): Collection
    {
        return $guardians
            ->groupBy(fn (Guardian $guardian): string => GuardianIdentityKey::for($guardian))
            ->map(function (Collection $group): GuardianIndexGroup {
                $records = $group->sortBy('id')->values();
                /** @var Guardian $primary */
                $primary = $records->first();

                $schoolLabels = $records
                    ->map(fn (Guardian $guardian): ?string => $guardian->school?->name_en ?: $guardian->school?->name_ar)
                    ->filter(fn (?string $label): bool => is_string($label) && trim($label) !== '')
                    ->unique()
                    ->values()
                    ->all();

                return new GuardianIndexGroup(
                    primary: $primary,
                    records: $records,
                    studentsCount: (int) $records->sum('students_count'),
                    schoolLabels: $schoolLabels,
                );
            })
            ->sortByDesc(fn (GuardianIndexGroup $group): int => (int) $group->primary->id)
            ->values();
    }

    /**
     * @param  Collection<int, Guardian>  $guardians
     */
    public function paginate(Collection $guardians, int $perPage, int $page, string $path, array $query = []): LengthAwarePaginator
    {
        $groups = $this->group($guardians);
        $total = $groups->count();
        $items = $groups->forPage($page, $perPage)->values()->all();

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $path,
                'query' => $query,
            ],
        );
    }

    /**
     * @param  list<Guardian>  $guardians
     */
    public function wrapSingleRecords(array $guardians): Collection
    {
        return collect($guardians)->map(function (Guardian $guardian): GuardianIndexGroup {
            $label = $guardian->school?->name_en ?: $guardian->school?->name_ar;

            return new GuardianIndexGroup(
                primary: $guardian,
                records: collect([$guardian]),
                studentsCount: (int) ($guardian->students_count ?? 0),
                schoolLabels: is_string($label) && trim($label) !== '' ? [trim($label)] : [],
            );
        });
    }
}
