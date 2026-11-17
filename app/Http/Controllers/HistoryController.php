<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\ExportRecord;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceProcessorService;
use App\Services\Shipping\ShippingLabelExtractionService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HistoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();
        $activeTab = $request->input('tab', 'batches');

        // 1. Batches Query
        $batchesQuery = DocumentBatch::with(['user', 'documents.invoice', 'documents.shippingLabels'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        if ($request->filled('batch_search')) {
            $bSearch = trim($request->batch_search);
            $batchesQuery->where('batch_name', 'like', "%{$bSearch}%");
        }

        $batches = $batchesQuery->paginate(10, ['*'], 'batches_page')->withQueryString();

        // 2. Invoices Processing History Query with Search & Filters
        $invoicesQuery = Invoice::with(['vendor', 'customer', 'document', 'shippingMatch.shippingLabel'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        if ($request->filled('invoice_number')) {
            $invoicesQuery->where('invoice_number', 'like', "%{$request->invoice_number}%");
        }
        if ($request->filled('order_number')) {
            $invoicesQuery->where('order_number', 'like', "%{$request->order_number}%");
        }
        if ($request->filled('po_number')) {
            $invoicesQuery->where('po_number', 'like', "%{$request->po_number}%");
        }
        if ($request->filled('shipping_id')) {
            $sId = trim($request->shipping_id);
            $invoicesQuery->where(function ($q) use ($sId) {
                $q->where('shipping_id', 'like', "%{$sId}%")
                  ->orWhereHas('shippingMatch', fn ($sq) => $sq->where('awb_number', 'like', "%{$sId}%"));
            });
        }
        if ($request->filled('buyer_name')) {
            $bName = trim($request->buyer_name);
            $invoicesQuery->where(function ($q) use ($bName) {
                $q->where('buyer_name', 'like', "%{$bName}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$bName}%"));
            });
        }
        if ($request->filled('invoice_date')) {
            $invoicesQuery->whereDate('invoice_date', $request->invoice_date);
        }
        if ($request->filled('processing_status')) {
            $invoicesQuery->where('processing_status', $request->processing_status);
        }

        $invoices = $invoicesQuery->paginate(15, ['*'], 'invoices_page')->withQueryString();

        // 3. Export Records Query
        $exportsQuery = ExportRecord::with('user')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        $exports = $exportsQuery->paginate(15, ['*'], 'exports_page')->withQueryString();

        // 4. Audit Logs Query
        $logsQuery = ActivityLog::with('user')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        if ($request->filled('log_search')) {
            $lSearch = trim($request->log_search);
            $logsQuery->where('description', 'like', "%{$lSearch}%")
                      ->orWhere('action', 'like', "%{$lSearch}%");
        }

        $logs = $logsQuery->paginate(20, ['*'], 'logs_page')->withQueryString();

        // Summary Statistics
        $totalBatches = DocumentBatch::when($userId, fn ($q) => $q->where('user_id', $userId))->count();
        $totalInvoicesCount = Invoice::when($userId, fn ($q) => $q->where('user_id', $userId))->count();
        $matchedCount = Invoice::when($userId, fn ($q) => $q->where('user_id', $userId))->where('matching_status', 'matched')->count();
        $unmatchedCount = Invoice::when($userId, fn ($q) => $q->where('user_id', $userId))->whereIn('matching_status', ['unmatched', 'mismatch', 'review_needed'])->count();
        $failedDocsCount = Document::when($userId, fn ($q) => $q->where('user_id', $userId))->where('extraction_status', Document::STATUS_FAILED)->count();

        return view('history.index', compact(
            'activeTab',
            'batches',
            'invoices',
            'exports',
            'logs',
            'totalBatches',
            'totalInvoicesCount',
            'matchedCount',
            'unmatchedCount',
            'failedDocsCount'
        ));
    }

    /**
     * Reprocess all documents in an entire upload batch.
     */
    public function reprocessBatch(
        DocumentBatch $batch,
        InvoiceProcessorService $processor,
        ShippingLabelExtractionService $shippingExtractor
    ) {
        if (!auth()->user()->isAdmin() && $batch->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $documents = $batch->documents;
        $successCount = 0;
        $failCount = 0;

        foreach ($documents as $doc) {
            try {
                $doc->update(['extraction_status' => Document::STATUS_PROCESSING]);

                if ($doc->isShippingLabel()) {
                    $shippingExtractor->extractDocument($doc);
                } else {
                    $processor->processDocument($doc);
                }
                $successCount++;
            } catch (Exception $e) {
                $doc->update([
                    'extraction_status' => Document::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                $failCount++;
            }
        }

        $batch->recalculateStatus();

        ActivityLog::record(
            'batch_reprocessed',
            "Reprocessed Batch '{$batch->batch_name}': {$successCount} succeeded, {$failCount} failed.",
            $batch
        );

        return back()->with('success', "Batch reprocessing finished: {$successCount} processed, {$failCount} errors.");
    }

    /**
     * Delete an upload batch and its documents after user confirmation.
     */
    public function destroyBatch(DocumentBatch $batch)
    {
        if (!auth()->user()->isAdmin() && $batch->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $batchName = $batch->batch_name;

        // Delete physical files
        foreach ($batch->documents as $doc) {
            if ($doc->stored_path && Storage::disk('local')->exists($doc->stored_path)) {
                Storage::disk('local')->delete($doc->stored_path);
            }
            $doc->delete();
        }

        $batch->delete();

        ActivityLog::record(
            'batch_deleted',
            "Deleted Upload Batch '{$batchName}' and all associated documents."
        );

        return back()->with('success', "Batch '{$batchName}' deleted successfully.");
    }

    /**
     * Reprocess a single document.
     */
    public function reprocessDocument(
        Document $document,
        InvoiceProcessorService $processor,
        ShippingLabelExtractionService $shippingExtractor
    ) {
        if (!auth()->user()->isAdmin() && $document->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        try {
            $document->update(['extraction_status' => Document::STATUS_PROCESSING]);

            if ($document->isShippingLabel()) {
                $shippingExtractor->extractDocument($document);
            } else {
                $processor->processDocument($document);
            }

            $document->batch?->recalculateStatus();

            ActivityLog::record(
                'document_reprocessed',
                "Reprocessed document '{$document->original_filename}' successfully.",
                $document
            );

            return back()->with('success', "Document '{$document->original_filename}' reprocessed successfully.");
        } catch (Exception $e) {
            $document->update([
                'extraction_status' => Document::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $document->batch?->recalculateStatus();

            return back()->with('error', "Reprocessing failed: " . $e->getMessage());
        }
    }
}
