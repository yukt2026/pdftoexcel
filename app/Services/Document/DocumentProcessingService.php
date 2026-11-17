<?php

namespace App\Services\Document;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentPage;
use App\Services\OCR\InvoiceOcrService;
use App\Services\Pdf\PdfPageRendererService;
use App\Services\Pdf\PdfTextExtractionService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentProcessingService
{
    public function __construct(
        protected PdfTextExtractionService $textExtractor,
        protected PdfPageRendererService $pageRenderer,
        protected InvoiceOcrService $ocrService
    ) {}

    /**
     * Resolve absolute disk path of stored document.
     */
    public function getAbsolutePath(string $storedPath): string
    {
        if (file_exists($storedPath)) {
            return $storedPath;
        }

        $basePath = base_path($storedPath);
        if (file_exists($basePath)) {
            return $basePath;
        }

        $diskPath = Storage::disk('local')->path($storedPath);
        if (file_exists($diskPath)) {
            return $diskPath;
        }

        $legacyAppPath = storage_path('app/' . $storedPath);
        if (file_exists($legacyAppPath)) {
            return $legacyAppPath;
        }

        return $diskPath;
    }

    /**
     * Execute full text extraction and OCR pipeline on a Document.
     *
     * @param Document $document
     * @return Document
     * @throws Exception
     */
    public function process(Document $document): Document
    {
        $document->update([
            'extraction_status' => Document::STATUS_PROCESSING,
            'status' => 'parsing',
            'error_message' => null,
        ]);

        $filePath = $this->getAbsolutePath($document->stored_path);

        if (!file_exists($filePath)) {
            $error = "Document file not found on disk: {$document->stored_path}";
            $document->update([
                'extraction_status' => Document::STATUS_FAILED,
                'status' => 'failed',
                'error_message' => $error,
            ]);
            $document->batch?->recalculateStatus();
            throw new Exception($error);
        }

        try {
            // 1. Text Extraction & Structural Analysis
            $extraction = $this->textExtractor->extractText($filePath);

            // Handle Password Protection
            if ($extraction['has_password']) {
                $document->update([
                    'has_password' => true,
                    'extraction_status' => Document::STATUS_FAILED,
                    'status' => 'failed',
                    'error_message' => $extraction['error_message'],
                ]);
                $document->batch?->recalculateStatus();

                ActivityLog::record(
                    'document_password_detected',
                    "Password-protected document detected: {$document->original_filename}",
                    $document
                );

                return $document;
            }

            // Handle Corrupted PDF
            if ($extraction['is_corrupted']) {
                $document->update([
                    'extraction_status' => Document::STATUS_FAILED,
                    'status' => 'failed',
                    'error_message' => $extraction['error_message'],
                ]);
                $document->batch?->recalculateStatus();

                ActivityLog::record(
                    'document_corrupted',
                    "Corrupted PDF detected: {$document->original_filename}",
                    $document
                );

                return $document;
            }

            $pageCount = $extraction['page_count'] ?: 1;
            $pagesData = $extraction['pages'];
            $ocrAppliedAny = false;
            $ocrStatus = 'none';
            $activeOcrProvider = null;
            $temporaryImages = [];

            // 2. Page-by-Page OCR Evaluation
            // If selectable text is unavailable on any page, attempt rendering & OCR
            foreach ($pagesData as $i => &$pData) {
                $pageNumber = $pData['page_number'];

                // If page is scanned or empty, render page image and invoke OCR
                if ($pData['is_scanned'] || $pData['status'] === 'failed' || $pData['status'] === 'empty') {
                    if ($this->pageRenderer->isAvailable() || $this->ocrService->isAvailable()) {
                        try {
                            $renderedImagePath = $this->pageRenderer->renderPageToImage($filePath, $pageNumber, 200);

                            if ($renderedImagePath && file_exists($renderedImagePath)) {
                                $temporaryImages[] = $renderedImagePath;

                                $ocrResult = $this->ocrService->extractText($renderedImagePath);

                                if ($ocrResult['success'] && !empty(trim($ocrResult['text']))) {
                                    $pData['text'] = $ocrResult['text'];
                                    $pData['char_count'] = mb_strlen(trim($ocrResult['text']));
                                    $pData['is_scanned'] = false;
                                    $pData['ocr_applied'] = true;
                                    $pData['ocr_provider'] = $ocrResult['provider'];
                                    $pData['status'] = 'completed';
                                    $pData['error'] = null;

                                    $ocrAppliedAny = true;
                                    $activeOcrProvider = $ocrResult['provider'];
                                } else {
                                    $pData['ocr_applied'] = false;
                                    $pData['error'] = $ocrResult['error'] ?? 'OCR completed with empty text.';
                                }
                            }
                        } catch (Throwable $oe) {
                            Log::warning("Page {$pageNumber} OCR rendering failed: " . $oe->getMessage());
                            $pData['error'] = "OCR failed: " . $oe->getMessage();
                        }
                    } else {
                        // Renderer / OCR unavailable
                        if (empty(trim($pData['text']))) {
                            $pData['error'] = "Scanned page with no selectable text, and OCR engine is not configured.";
                        }
                    }
                }
            }
            unset($pData);

            // Clean up temporary rendered images
            if (!empty($temporaryImages)) {
                $this->pageRenderer->cleanup($temporaryImages);
            }

            // 3. Assemble Full Raw Text with Page Demarcation
            $fullRawText = '';
            foreach ($pagesData as $p) {
                $fullRawText .= "\n--- Page {$p['page_number']} ---\n" . $p['text'];
            }
            $fullRawText = trim($fullRawText);

            if ($ocrAppliedAny) {
                $ocrStatus = 'completed';
            }

            // 4. Persist to Database in Transaction
            DB::transaction(function () use ($document, $pageCount, $fullRawText, $pagesData, $extraction, $ocrStatus, $activeOcrProvider) {
                // Delete existing page records if re-processing
                $document->pages()->delete();

                // Insert granular page records
                foreach ($pagesData as $p) {
                    DocumentPage::create([
                        'document_id' => $document->id,
                        'page_number' => $p['page_number'],
                        'extracted_text' => $p['text'],
                        'char_count' => $p['char_count'],
                        'is_scanned' => $p['is_scanned'] ?? false,
                        'ocr_applied' => $p['ocr_applied'] ?? false,
                        'ocr_provider' => $p['ocr_provider'] ?? null,
                        'status' => $p['status'] ?? 'completed',
                        'error_message' => $p['error'] ?? null,
                    ]);
                }

                // Update Document summary
                $document->update([
                    'total_pages' => $pageCount,
                    'page_count' => $pageCount,
                    'raw_text' => $fullRawText,
                    'page_wise_text' => $pagesData,
                    'is_scanned' => $extraction['is_scanned'] && !$ocrStatus,
                    'has_password' => false,
                    'ocr_status' => $ocrStatus,
                    'ocr_provider' => $activeOcrProvider,
                    'extraction_status' => Document::STATUS_COMPLETED,
                    'status' => 'completed',
                    'error_message' => null,
                ]);
            });

            $document->batch?->recalculateStatus();

            ActivityLog::record(
                'document_extracted',
                "Successfully extracted text from '{$document->original_filename}' ({$pageCount} pages, " . mb_strlen($fullRawText) . " chars).",
                $document,
                [
                    'page_count' => $pageCount,
                    'char_count' => mb_strlen($fullRawText),
                    'ocr_applied' => $ocrAppliedAny,
                    'ocr_provider' => $activeOcrProvider,
                ]
            );

            return $document;
        } catch (Throwable $e) {
            Log::error("DocumentProcessingService error on {$document->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $document->update([
                'extraction_status' => Document::STATUS_FAILED,
                'status' => 'failed',
                'error_message' => 'Processing error: ' . $e->getMessage(),
            ]);

            $document->batch?->recalculateStatus();

            throw $e;
        }
    }
}
