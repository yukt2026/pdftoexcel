<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceUploadRequest;
use App\Http\Requests\ShippingUploadRequest;
use App\Jobs\ProcessInvoiceDocumentJob;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\Invoice;
use App\Models\ShippingDocument;
use App\Models\ShippingLabel;
use App\Services\Invoice\InvoiceProcessorService;
use App\Services\Pdf\DocumentUploadService;
use App\Services\Pdf\PdfParserService;
use App\Services\Shipping\ShippingLabelExtractionService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DocumentUploadService $uploadService,
        protected PdfParserService $pdfParser
    ) {}

    // General Uploads Index
    public function index()
    {
        return $this->invoices();
    }

    // Page 2: Upload Invoices (Scoped)
    public function invoices()
    {
        $query = Document::where('document_type', Document::TYPE_INVOICE)
            ->orWhere(function ($q) {
                $q->whereNull('document_type')
                  ->where('file_type', 'pdf');
            })
            ->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $recentInvoiceUploads = $query->with(['invoice', 'invoices', 'batch'])->take(10)->get();

        $batchQuery = DocumentBatch::latest();
        if (!auth()->user()->isAdmin()) {
            $batchQuery->where('user_id', auth()->id());
        }
        $recentBatches = $batchQuery->take(5)->get();

        return view('uploads.invoices', compact('recentInvoiceUploads', 'recentBatches'));
    }

    // Page 3: Upload Shipping Labels (Scoped)
    public function shipping()
    {
        $shippingQuery = ShippingDocument::with('invoice')->latest();
        $invoicesQuery = Invoice::orderBy('invoice_number');

        if (!auth()->user()->isAdmin()) {
            $shippingQuery->where('user_id', auth()->id());
            $invoicesQuery->where('user_id', auth()->id());
        }

        $recentShippingUploads = $shippingQuery->take(10)->get();
        $invoices = $invoicesQuery->get();

        $batchQuery = DocumentBatch::where('batch_name', 'like', '%Shipping%')->latest();
        if (!auth()->user()->isAdmin()) {
            $batchQuery->where('user_id', auth()->id());
        }
        $recentShippingBatches = $batchQuery->take(5)->get();

        return view('uploads.shipping', compact('recentShippingUploads', 'invoices', 'recentShippingBatches'));
    }

    // Handle Invoice Uploads (Multiple PDFs, Batch Tracking, Duplicate Detection)
    public function store(InvoiceUploadRequest $request, InvoiceProcessorService $processor)
    {
        $files = $request->file('documents');
        $batchName = $request->input('batch_name');
        $autoProcess = $request->boolean('process_immediately', true);

        $result = $this->uploadService->processBatchUpload(
            user: auth()->user(),
            documentType: Document::TYPE_INVOICE,
            files: $files,
            batchName: $batchName,
            processImmediately: $autoProcess,
            invoiceProcessor: $processor
        );

        $uploadedCount = count($result['uploaded']);
        $duplicateCount = count($result['duplicates']);
        $errorCount = count($result['errors']);

        // Format message
        $messages = [];
        if ($uploadedCount > 0) {
            $messages[] = "Successfully uploaded {$uploadedCount} invoice PDF(s) in Batch '{$result['batch']->batch_name}' ({$result['total_pages']} total pages).";
        }

        $warningMessages = [];
        if ($duplicateCount > 0) {
            $dupNames = array_map(fn ($d) => "'{$d['filename']}'", $result['duplicates']);
            $warningMessages[] = "{$duplicateCount} duplicate file(s) skipped (already uploaded): " . implode(', ', $dupNames) . ".";
        }

        if ($errorCount > 0) {
            $warningMessages = array_merge($warningMessages, $result['errors']);
        }

        // Return JSON for AJAX drag-and-drop progress uploads
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $uploadedCount > 0,
                'batch_id' => $result['batch']->id,
                'batch_name' => $result['batch']->batch_name,
                'uploaded_count' => $uploadedCount,
                'total_pages' => $result['total_pages'],
                'duplicates' => $result['duplicates'],
                'errors' => $result['errors'],
                'redirect_url' => route('batches.show', $result['batch']),
            ], $uploadedCount > 0 ? 200 : 422);
        }

        if ($uploadedCount > 0) {
            $redirect = redirect()->route('batches.show', $result['batch'])
                ->with('success', implode(' ', $messages));

            if (!empty($warningMessages)) {
                $redirect->with('warning', implode(' ', $warningMessages));
            }

            return $redirect;
        }

        return back()->withErrors(array_merge($warningMessages, ['No new files could be uploaded.']));
    }

    // Handle Shipping Label Uploads (Multiple PDFs, Batch Tracking)
    public function storeShipping(ShippingUploadRequest $request, InvoiceProcessorService $processor)
    {
        $files = $request->file('shipping_files');
        $batchName = $request->input('batch_name');
        $carrier = $request->input('carrier_name', 'FedEx Express');
        $invoiceId = $request->input('invoice_id');

        $result = $this->uploadService->processBatchUpload(
            user: auth()->user(),
            documentType: Document::TYPE_SHIPPING_LABEL,
            files: $files,
            batchName: $batchName,
            processImmediately: false,
            extraAttributes: [
                'carrier_name' => $carrier,
                'invoice_id' => $invoiceId,
            ]
        );

        $uploadedCount = count($result['uploaded']);
        $duplicateCount = count($result['duplicates']);
        $errorCount = count($result['errors']);

        $messages = [];
        if ($uploadedCount > 0) {
            $messages[] = "Successfully uploaded {$uploadedCount} shipping label PDF(s) in Batch '{$result['batch']->batch_name}'.";
        }

        $warningMessages = [];
        if ($duplicateCount > 0) {
            $dupNames = array_map(fn ($d) => "'{$d['filename']}'", $result['duplicates']);
            $warningMessages[] = "{$duplicateCount} duplicate shipping label(s) skipped: " . implode(', ', $dupNames) . ".";
        }

        if ($errorCount > 0) {
            $warningMessages = array_merge($warningMessages, $result['errors']);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $uploadedCount > 0,
                'batch_id' => $result['batch']->id,
                'batch_name' => $result['batch']->batch_name,
                'uploaded_count' => $uploadedCount,
                'duplicates' => $result['duplicates'],
                'errors' => $result['errors'],
                'redirect_url' => route('batches.show', $result['batch']),
            ], $uploadedCount > 0 ? 200 : 422);
        }

        if ($uploadedCount > 0) {
            $redirect = redirect()->route('batches.show', $result['batch'])
                ->with('success', implode(' ', $messages));

            if (!empty($warningMessages)) {
                $redirect->with('warning', implode(' ', $warningMessages));
            }

            return $redirect;
        }

        return back()->withErrors(array_merge($warningMessages, ['No new shipping files could be uploaded.']));
    }

    // Pre-upload file validation and duplicate check endpoint
    public function inspectFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $file = $request->file('file');
        $hash = $this->uploadService->computeFileHash($file);
        $duplicate = $this->uploadService->findDuplicate($hash, auth()->id());

        // Quick page count detection
        $pageCount = $this->pdfParser->getPageCount($file->getRealPath());

        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'formatted_size' => number_format($file->getSize() / 1024, 1) . ' KB',
            'hash' => $hash,
            'is_duplicate' => $duplicate !== null,
            'duplicate_info' => $duplicate ? [
                'id' => $duplicate->id,
                'original_filename' => $duplicate->original_filename,
                'uploaded_at' => $duplicate->created_at->toFormattedDateString(),
            ] : null,
            'page_count' => $pageCount,
        ]);
    }

    // Quick Action: Batch Process Pending Documents (Scoped to user's documents if not admin)
    public function batchProcess(InvoiceProcessorService $processor, ShippingLabelExtractionService $shippingExtractor)
    {
        $query = Document::whereIn('extraction_status', [Document::STATUS_PENDING, Document::STATUS_PROCESSING])
            ->orWhereIn('status', ['uploaded', 'parsing']);

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $pendingDocs = $query->get();
        $count = 0;

        foreach ($pendingDocs as $doc) {
            try {
                if ($doc->isShippingLabel()) {
                    $shippingExtractor->extractDocument($doc);
                } else {
                    $processor->processDocument($doc);
                }
                $count++;
            } catch (Exception $e) {
                $doc->update([
                    'status' => 'failed',
                    'extraction_status' => Document::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                $doc->batch?->recalculateStatus();
            }
        }

        return redirect()->route('processing.index')
            ->with('success', "Pipeline batch execution completed: {$count} document(s) processed.");
    }

    // Single Document re-process trigger (Policy protected)
    public function processSingle(Document $document, InvoiceProcessorService $processor, ShippingLabelExtractionService $shippingExtractor)
    {
        $this->authorize('process', $document);

        try {
            if ($document->isShippingLabel()) {
                $labels = $shippingExtractor->extractDocument($document);
                return redirect()->route('shipping.index')
                    ->with('success', "Extracted {$labels->count()} shipping label(s) from '{$document->original_filename}'.");
            }

            $invoice = $processor->processDocument($document);
            return redirect()->route('invoices.show', $invoice)
                ->with('success', "Document #{$document->id} processed into Invoice #{$invoice->invoice_number}.");
        } catch (Exception $e) {
            return back()->with('warning', "Processing error: " . $e->getMessage());
        }
    }

    // Page 5: Processing Queue (Scoped)
    public function queueStatus()
    {
        $query = Document::with(['user', 'invoice', 'batch'])->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $documents = $query->paginate(20);

        $pendingCount = (clone $query)->whereIn('extraction_status', [Document::STATUS_PENDING, Document::STATUS_PROCESSING])
            ->orWhereIn('status', ['uploaded', 'parsing'])->count();
        $completedCount = (clone $query)->where(function ($q) {
            $q->where('extraction_status', Document::STATUS_COMPLETED)
              ->orWhere('status', 'completed');
        })->count();
        $failedCount = (clone $query)->where(function ($q) {
            $q->where('extraction_status', Document::STATUS_FAILED)
              ->orWhere('status', 'failed');
        })->count();

        return view('processing.index', compact('documents', 'pendingCount', 'completedCount', 'failedCount'));
    }
}
