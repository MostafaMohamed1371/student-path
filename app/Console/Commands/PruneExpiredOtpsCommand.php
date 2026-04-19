<?php

namespace App\Console\Commands;

use App\Models\OtpCode;
use Illuminate\Console\Command;

/**
 * Deletes OTP rows that are safe to remove after a retention period.
 *
 * Suggested scheduler entry (routes/console.php):
 * Schedule::command('otp:prune')->daily();
 */
class PruneExpiredOtpsCommand extends Command
{
    protected $signature = 'otp:prune {--days=7 : Minimum age in days before a record can be pruned}';

    protected $description = 'Prune OTP codes that have been expired or verified for longer than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        // Only remove rows past retention so recent audit/debug data can remain briefly.
        $deleted = OtpCode::query()
            ->where(function ($q) use ($threshold) {
                $q->where('expires_at', '<', $threshold)
                    ->orWhere(function ($q2) use ($threshold) {
                        $q2->whereNotNull('verified_at')
                            ->where('verified_at', '<', $threshold);
                    });
            })
            ->delete();

        $this->info("Deleted {$deleted} OTP record(s).");

        return self::SUCCESS;
    }
}
