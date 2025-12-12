<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Models\Central\PendingSignup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup Expired Signups Job
 *
 * Scheduled job that cleans up expired pending signups.
 * Should be run daily to remove stale signup attempts.
 */
class CleanupExpiredSignupsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  int  $olderThanHours  Delete signups older than this many hours (default: 48)
     */
    public function __construct(
        public int $olderThanHours = 48
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffDate = now()->subHours($this->olderThanHours);

        // Delete expired signups that were never completed
        $expiredCount = PendingSignup::query()
            ->where('status', PendingSignup::STATUS_PENDING)
            ->where(function ($query) use ($cutoffDate) {
                $query->where('expires_at', '<', now())
                    ->orWhere('created_at', '<', $cutoffDate);
            })
            ->delete();

        // Delete failed signups older than cutoff
        $failedCount = PendingSignup::query()
            ->where('status', PendingSignup::STATUS_FAILED)
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        // Delete processing signups that are stuck (older than 24 hours in processing)
        $stuckCount = PendingSignup::query()
            ->where('status', PendingSignup::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subHours(24))
            ->delete();

        $totalDeleted = $expiredCount + $failedCount + $stuckCount;

        if ($totalDeleted > 0) {
            Log::info('CleanupExpiredSignupsJob: Cleaned up pending signups', [
                'expired' => $expiredCount,
                'failed' => $failedCount,
                'stuck' => $stuckCount,
                'total' => $totalDeleted,
                'older_than_hours' => $this->olderThanHours,
            ]);
        }
    }
}
