<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\Invoice\InvoiceExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceExtractionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public Document $document,
        public bool $forceReprocess = false
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    /**
     * Execute the job: runs AI Indian invoice extraction and persists structured results.
     */
    public function handle(InvoiceExtractionService $extractionService): void
    {
        Log::info("Starting AI Invoice Extraction Job for Document #{$this->document->id} ({$this->document->original_filename})");

        try {
            $result = $extractionService->extractAndPersist($this->document, $this->forceReprocess);

            Log::info("Completed AI Invoice Extraction Job for Document #{$this->document->id}: status={$result->status}, confidence={$result->overall_confidence}%");
        } catch (Throwable $e) {
            Log::error("InvoiceExtractionJob error on Document #{$this->document->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->document->update([
                'extraction_status' => Document::STATUS_FAILED,
                'error_message' => "AI extraction failed: {$e->getMessage()}",
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("InvoiceExtractionJob permanently failed for Document #{$this->document->id}: " . $exception->getMessage());

        $this->document->update([
            'extraction_status' => Document::STATUS_FAILED,
            'error_message' => "Permanent extraction error: {$exception->getMessage()}",
        ]);

        ActivityLog::record(
            'invoice_extraction_job_failed',
            "AI invoice extraction permanently failed for '{$this->document->original_filename}': {$exception->getMessage()}",
            $this->document
        );
    }
}
