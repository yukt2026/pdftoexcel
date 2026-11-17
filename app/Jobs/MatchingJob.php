<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Services\Matching\InvoiceMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MatchingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public ?int $userId = null
    ) {}

    public function uniqueId(): string
    {
        return 'user_' . ($this->userId ?? 'all');
    }

    /**
     * Execute the job: runs transactional intelligent invoice and shipping label matching.
     */
    public function handle(InvoiceMatchingService $matchingService): void
    {
        Log::info("Starting MatchingJob for user: " . ($this->userId ?? 'ALL'));

        try {
            $summary = $matchingService->runBatchMatching($this->userId);

            Log::info("MatchingJob completed: {$summary['matched']} matched, {$summary['needs_review']} needs review, {$summary['duplicates']} duplicates.");
        } catch (Throwable $e) {
            Log::error("MatchingJob failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("MatchingJob permanently failed: " . $exception->getMessage());

        ActivityLog::record(
            'matching_job_failed',
            "Automated matching job failed: {$exception->getMessage()}"
        );
    }
}
