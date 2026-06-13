<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use Illuminate\Support\Collection;

final class GuardianIndexGroup
{
    /**
     * @param  Collection<int, Guardian>  $records
     * @param  list<string>  $schoolLabels
     */
    public function __construct(
        public readonly Guardian $primary,
        public readonly Collection $records,
        public readonly int $studentsCount,
        public readonly array $schoolLabels,
    ) {}

    public function isMultiSchool(): bool
    {
        return count($this->schoolLabels) > 1;
    }
}
