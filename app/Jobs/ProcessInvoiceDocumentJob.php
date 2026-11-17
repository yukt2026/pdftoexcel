<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Invoice\InvoiceProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInvoiceDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public Document $document)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceProcessorService $processor): void
    {
        Log::info("Processing document queued job started for doc #{$this->document->id}");
        $processor->processDocument($this->document);
    }
}
