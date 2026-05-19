<?php

namespace App\Console\Commands;

use App\Services\Trips\DriverTripModuleService;
use Illuminate\Console\Command;

/**
 * Auto-cancel trips that were never started after the driver start window,
 * and auto-complete trips that passed their scheduled end time while in progress.
 */
class SyncTripStatusesCommand extends Command
{
    protected $signature = 'trips:sync-statuses';

    protected $description = 'Apply time-based CANCELLED / COMPLETED status updates for due trips';

    public function handle(DriverTripModuleService $driverTripModuleService): int
    {
        $updated = $driverTripModuleService->syncDueTripStatuses();

        $this->info("Updated {$updated} trip(s).");

        return self::SUCCESS;
    }
}
