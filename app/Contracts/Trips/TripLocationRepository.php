<?php

namespace App\Contracts\Trips;

interface TripLocationRepository
{
    /**
     * @param  array<string, mixed>  $tracking
     */
    public function write(int $tripHistoryId, array $tracking): void;

    /**
     * @return array<string, mixed>|null
     */
    public function read(int $tripHistoryId): ?array;

    public function deactivate(int $tripHistoryId): void;

    public function trackingPath(int $tripHistoryId): string;
}
