<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\Shipping\ShippingLabelExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingLabelJob implements ShouldQueue, ShouldBeUnique
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
     * Execute the job: extract shipping labels across all pages of the document.
     */
    public function handle(ShippingLabelExtractionService $extractionService): void
    {
        Log::info("Starting Shipping Label Extraction Job for Document #{$this->document->id} ({$this->document->original_filename})");

        try {
            $labels = $extractionService->extractDocument($this->document, $this->forceReprocess);

            Log::info("Completed Shipping Label Extraction Job for Document #{$this->document->id}: extracted {$labels->count()} label(s).");
        } catch (Throwable $e) {
            Log::error("ShippingLabelJob failed for Document #{$this->document->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->document->update([
                'extraction_status' => Document::STATUS_FAILED,
                'error_message' => "Shipping label extraction failed: {$e->getMessage()}",
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ShippingLabelJob permanently failed for Document #{$this->document->id}: " . $exception->getMessage());

        $this->document->update([
            'extraction_status' => Document::STATUS_FAILED,
            'error_message' => "Permanent shipping label extraction failure: {$exception->getMessage()}",
        ]);

        ActivityLog::record(
            'shipping_label_job_failed',
            "Shipping label extraction job permanently failed for '{$this->document->original_filename}': {$exception->getMessage()}",
            $this->document
        );
    }
}
