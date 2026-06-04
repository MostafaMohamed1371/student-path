<?php

namespace App\Services\Trips;

final class TripRequestSubmissionPlan
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly ?int $driverId,
        public readonly ?int $tripHistoryId,
        public readonly ?string $targetShift,
        public readonly array $snapshot,
    ) {}
}
