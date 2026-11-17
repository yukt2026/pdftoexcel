<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Document\DocumentProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExtractDocumentTextJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Document $document
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    /**
     * Execute the job: runs PDF extraction and OCR pipeline.
     */
    public function handle(DocumentProcessingService $processor): void
    {
        Log::info("Starting background text extraction job for Document #{$this->document->id} ({$this->document->original_filename})");

        try {
            $processor->process($this->document);
            if ($this->document->document_type === Document::TYPE_INVOICE || empty($this->document->document_type)) {
                app(\App\Services\Invoice\InvoiceProcessorService::class)->processDocument($this->document);
            } elseif ($this->document->document_type === Document::TYPE_SHIPPING_LABEL) {
                app(\App\Services\Shipping\ShippingLabelExtractionService::class)->extractDocument($this->document);
                app(\App\Services\Matching\InvoiceMatchingService::class)->runBatchMatching($this->document->user_id);
            }
            Log::info("Completed background text extraction job for Document #{$this->document->id}");
        } catch (Throwable $e) {
            Log::error("ExtractDocumentTextJob failed for Document #{$this->document->id}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
