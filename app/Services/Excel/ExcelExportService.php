<?php

namespace App\Services\Excel;

use App\Exports\InvoiceDetailExport;
use App\Exports\InvoiceWorkbookExport;
use App\Jobs\ExportInvoicesJob;
use App\Models\ActivityLog;
use App\Models\DocumentBatch;
use App\Models\ExportRecord;
use App\Models\Invoice;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportService
{
    /**
     * Generate a unique, professional filename for an export.
     */
    public function generateFilename(string $option = 'All'): string
    {
        $cleanOption = ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', $option));
        $timestamp = date('Ymd_His');
        $entropy = bin2hex(random_bytes(3));

        return "Invoices_Export_{$cleanOption}_{$timestamp}_{$entropy}.xlsx";
    }

    /**
     * Build the scoped invoice query based on user and filter criteria.
     */
    public function buildQuery(string $option = 'all', array $filters = [], ?int $userId = null): Builder
    {
        $query = Invoice::with(['vendor', 'customer', 'items', 'shippingMatch.shippingLabel', 'document'])->latest();

        // Enforce user scoping if not admin
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        switch ($option) {
            case 'selected':
                $invoiceIds = $filters['invoice_ids'] ?? [];
                if (!empty($invoiceIds)) {
                    $query->whereIn('id', (array) $invoiceIds);
                }
                break;

            case 'date_range':
                if (!empty($filters['start_date'])) {
                    $query->whereDate('invoice_date', '>=', $filters['start_date']);
                }
                if (!empty($filters['end_date'])) {
                    $query->whereDate('invoice_date', '<=', $filters['end_date']);
                }
                break;

            case 'batch':
                $batchId = $filters['batch_id'] ?? null;
                if (!empty($batchId)) {
                    $query->whereHas('document', function ($q) use ($batchId) {
                        $q->where('batch_id', $batchId);
                    });
                }
                break;

            case 'matched_only':
                $query->where('matching_status', 'matched');
                break;

            case 'unmatched_only':
                $query->whereIn('matching_status', ['unmatched', 'mismatch', 'review_needed']);
                break;

            case 'all':
            default:
                // No additional filtering
                break;
        }

        return $query;
    }

    /**
     * Export invoices using the 2-sheet workbook format.
     * Stores file securely to disk, logs history, and streams download response.
     */
    public function exportWorkbook(
        string $option = 'all',
        array $filters = [],
        ?int $userId = null,
        bool $forceQueue = false
    ): BinaryFileResponse|ExportRecord {
        $query = $this->buildQuery($option, $filters, $userId);
        $totalCount = $query->count();
        $filename = $this->generateFilename($option);
        $relativeStoragePath = 'exports/' . $filename;

        // Auto-queue for large exports (> 500 records) or when forced
        if ($forceQueue || $totalCount > 500) {
            $invoiceIds = $query->pluck('id')->toArray();

            $record = ExportRecord::create([
                'user_id' => $userId ?? (auth()->id() ?? 1),
                'export_name' => "Invoices Export (" . ucwords(str_replace('_', ' ', $option)) . ")",
                'format' => 'xlsx',
                'file_path' => $relativeStoragePath,
                'total_records' => $totalCount,
                'filter_criteria' => array_merge(['option' => $option], $filters),
                'status' => 'processing',
            ]);

            ExportInvoicesJob::dispatch($record, $invoiceIds, $filename);

            return $record;
        }

        $invoices = $query->get();

        // 1. Store securely in local storage
        if (!Storage::disk('local')->exists('exports')) {
            Storage::disk('local')->makeDirectory('exports');
        }

        Excel::store(new InvoiceWorkbookExport($invoices), $relativeStoragePath, 'local');

        // 2. Record export history
        $record = ExportRecord::create([
            'user_id' => $userId ?? (auth()->id() ?? 1),
            'export_name' => "Invoices Export (" . ucwords(str_replace('_', ' ', $option)) . ")",
            'format' => 'xlsx',
            'file_path' => $relativeStoragePath,
            'total_records' => $invoices->count(),
            'filter_criteria' => array_merge(['option' => $option], $filters),
            'status' => 'completed',
        ]);

        ActivityLog::record(
            'excel_exported',
            "Generated Excel workbook: {$record->export_name} with 2 sheets ({$invoices->count()} records).",
            $record
        );

        // 3. Return direct download response
        return Excel::download(new InvoiceWorkbookExport($invoices), $filename);
    }

    /**
     * Download a single invoice sheet.
     */
    public function downloadSingleInvoice(Invoice $invoice, ?string $filename = null): BinaryFileResponse
    {
        $cleanNumber = preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoice->invoice_number);
        $filename = $filename ?: "Invoice_{$cleanNumber}.xlsx";

        $record = ExportRecord::create([
            'user_id' => auth()->id() ?? 1,
            'export_name' => "Single Invoice Export (#{$invoice->invoice_number})",
            'format' => 'xlsx',
            'file_path' => 'exports/' . $filename,
            'total_records' => 1,
            'filter_criteria' => ['invoice_id' => $invoice->id],
            'status' => 'completed',
        ]);

        ActivityLog::record('excel_exported', "Generated single invoice Excel: #{$invoice->invoice_number}", $invoice);

        return Excel::download(new InvoiceWorkbookExport(collect([$invoice])), $filename);
    }

    /**
     * Download an existing export file from secure storage.
     */
    public function downloadStoredFile(ExportRecord $export): BinaryFileResponse
    {
        $path = Storage::disk('local')->path($export->file_path);

        if (!file_exists($path)) {
            // Check direct storage/app path
            $fallbackPath = storage_path('app/' . $export->file_path);
            if (file_exists($fallbackPath)) {
                $path = $fallbackPath;
            } else {
                throw new Exception("The export file [{$export->file_path}] could not be found in storage.");
            }
        }

        $basename = basename($export->file_path);

        return response()->download($path, $basename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
