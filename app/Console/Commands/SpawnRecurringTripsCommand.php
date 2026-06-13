<?php

namespace App\Console\Commands;

use App\Services\Trips\RecurringTripSpawner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SpawnRecurringTripsCommand extends Command
{
    protected $signature = 'trips:spawn-recurring {--date= : Calendar day (Y-m-d) to spawn for; default today}';

    protected $description = 'Create daily trip instances from recurring templates on school working days';

    public function handle(RecurringTripSpawner $spawner): int
    {
        $dateOption = $this->option('date');
        $day = is_string($dateOption) && trim($dateOption) !== ''
            ? Carbon::parse(trim($dateOption))->startOfDay()
            : now()->startOfDay();

        $created = $spawner->spawnAllForDay($day);

        $this->info("Spawned {$created} recurring trip(s) for {$day->toDateString()}.");

        return self::SUCCESS;
    }
}
