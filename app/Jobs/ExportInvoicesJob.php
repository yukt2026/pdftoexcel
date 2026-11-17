<?php

namespace App\Jobs;

use App\Exports\InvoiceWorkbookExport;
use App\Models\ActivityLog;
use App\Models\ExportRecord;
use App\Models\Invoice;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExportInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public ExportRecord $exportRecord,
        public array $invoiceIds,
        public string $filename
    ) {}

    public function handle(): void
    {
        try {
            $invoices = Invoice::with(['vendor', 'customer', 'items', 'shippingMatch.shippingLabel'])
                ->whereIn('id', $this->invoiceIds)
                ->latest()
                ->get();

            $relativeStoragePath = 'exports/' . $this->filename;

            // Store Excel file using Maatwebsite Excel
            Excel::store(new InvoiceWorkbookExport($invoices), $relativeStoragePath, 'local');

            $this->exportRecord->update([
                'file_path' => $relativeStoragePath,
                'total_records' => $invoices->count(),
                'status' => 'completed',
            ]);

            ActivityLog::record(
                'excel_export_completed',
                "Completed background Excel export: {$this->exportRecord->export_name} ({$invoices->count()} records).",
                $this->exportRecord
            );
        } catch (Exception $e) {
            Log::error("ExportInvoicesJob failed for ExportRecord #{$this->exportRecord->id}: " . $e->getMessage());

            $this->exportRecord->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}
