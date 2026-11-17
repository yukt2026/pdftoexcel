<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInvoiceDocumentJob;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Services\Document\DocumentProcessingService;
use App\Services\Invoice\InvoiceProcessorService;
use App\Services\Pdf\DocumentUploadService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DocumentUploadService $uploadService
    ) {}

    /**
     * List all upload batches (Upload History).
     */
    public function batches(Request $request)
    {
        $query = DocumentBatch::with(['user', 'documents'])->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('batch_name', 'like', "%{$search}%")
                  ->orWhereHas('documents', function ($dq) use ($search) {
                      $dq->where('original_filename', 'like', "%{$search}%");
                  });
            });
        }

        $batches = $query->paginate(15)->withQueryString();

        $stats = [
            'total_batches' => (clone $query)->count(),
            'total_files' => (clone $query)->sum('total_files'),
            'total_pages' => (clone $query)->sum('total_pages'),
            'has_errors' => (clone $query)->whereIn('status', [DocumentBatch::STATUS_HAS_ERRORS, DocumentBatch::STATUS_FAILED])->count(),
        ];

        return view('uploads.batches', compact('batches', 'stats'));
    }

    /**
     * Show documents inside a specific batch.
     */
    public function showBatch(DocumentBatch $batch)
    {
        $this->authorize('view', $batch);

        $documents = $batch->documents()->with('invoice')->latest()->paginate(25);

        return view('uploads.batch-detail', compact('batch', 'documents'));
    }

    /**
     * Securely download a document PDF.
     */
    public function download(Document $document)
    {
        $this->authorize('view', $document);

        $filePath = $this->uploadService->getAbsolutePath($document->stored_path);

        if (!file_exists($filePath)) {
            abort(404, 'The requested document file could not be found in secure storage.');
        }

        ActivityLog::record(
            'document_downloaded',
            "Downloaded file: {$document->original_filename}",
            $document
        );

        return response()->download($filePath, $document->original_filename, [
            'Content-Type' => $document->mime_type ?: 'application/pdf',
        ]);
    }

    /**
     * Securely preview a document inline in browser.
     */
    public function preview(Document $document)
    {
        $this->authorize('view', $document);

        $filePath = $this->uploadService->getAbsolutePath($document->stored_path);

        if (!file_exists($filePath)) {
            abort(404, 'The requested document file could not be found.');
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_filename) . '"',
        ]);
    }

    /**
     * View extracted raw text and page breakdown for debugging.
     */
    public function rawText(Document $document)
    {
        $this->authorize('view', $document);

        $document->load('pages');

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'id' => $document->id,
                'filename' => $document->original_filename,
                'total_pages' => $document->total_pages ?: $document->page_count ?: 1,
                'char_count' => mb_strlen($document->raw_text ?: ''),
                'raw_text' => $document->raw_text,
                'is_scanned' => $document->is_scanned,
                'ocr_status' => $document->ocr_status,
                'ocr_provider' => $document->ocr_provider,
                'pages' => $document->pages,
            ]);
        }

        return view('uploads.document-raw-text', compact('document'));
    }

    /**
     * Retry failed document extraction.
     */
    public function retry(Document $document, DocumentProcessingService $processor)
    {
        $this->authorize('process', $document);

        try {
            $processor->process($document);
            return back()->with('success', "Document #{$document->id} text extraction reprocessed successfully.");
        } catch (Exception $e) {
            return back()->with('warning', "Retry processing failed: " . $e->getMessage());
        }
    }

    /**
     * Retry all failed documents in a batch.
     */
    public function retryBatch(DocumentBatch $batch, DocumentProcessingService $processor)
    {
        $this->authorize('retry', $batch);

        $failedDocs = $batch->documents()
            ->where(function ($q) {
                $q->where('extraction_status', Document::STATUS_FAILED)
                  ->orWhere('status', 'failed');
            })
            ->get();

        if ($failedDocs->isEmpty()) {
            return back()->with('info', 'There are no failed documents in this batch.');
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($failedDocs as $doc) {
            try {
                $processor->process($doc);
                $succeeded++;
            } catch (Exception) {
                $failed++;
            }
        }

        $batch->recalculateStatus();

        return back()->with('success', "Batch retry completed: {$succeeded} succeeded, {$failed} still requiring attention.");
    }

    /**
     * Delete a single document and remove its file.
     */
    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        $batch = $document->batch;

        if (Storage::disk('local')->exists($document->stored_path)) {
            Storage::disk('local')->delete($document->stored_path);
        }

        $filename = $document->original_filename;
        $document->delete();

        $batch?->recalculateStatus();

        ActivityLog::record(
            'document_deleted',
            "Deleted document file: {$filename}"
        );

        return back()->with('success', "Document '{$filename}' deleted successfully.");
    }

    /**
     * Delete an entire batch and all associated documents.
     */
    public function destroyBatch(DocumentBatch $batch)
    {
        $this->authorize('delete', $batch);

        $batchName = $batch->batch_name;

        foreach ($batch->documents as $doc) {
            if (Storage::disk('local')->exists($doc->stored_path)) {
                Storage::disk('local')->delete($doc->stored_path);
            }
            $doc->delete();
        }

        $batch->delete();

        ActivityLog::record(
            'batch_deleted',
            "Deleted upload batch '{$batchName}' and all associated documents."
        );

        return redirect()->route('batches.index')->with('success', "Batch '{$batchName}' and all associated files deleted successfully.");
    }
}
