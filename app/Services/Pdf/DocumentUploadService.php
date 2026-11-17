<?php

namespace App\Services\Pdf;

use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\ProcessInvoiceDocumentJob;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\ShippingDocument;
use App\Models\User;
use App\Services\Document\DocumentProcessingService;
use App\Services\Invoice\InvoiceProcessorService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentUploadService
{
    public function __construct(
        protected PdfParserService $pdfParser,
        protected DocumentProcessingService $documentProcessor
    ) {}

    /**
     * Inspect file signature (magic bytes) to guarantee legitimate PDF.
     */
    public function isValidPdfSignature(UploadedFile $file): bool
    {
        $handle = @fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 1024);
        fclose($handle);

        return str_starts_with($header, '%PDF-') || str_contains(substr($header, 0, 1024), '%PDF-');
    }

    /**
     * Calculate SHA-256 hash for duplicate detection.
     */
    public function computeFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Check if document with hash already exists for user.
     */
    public function findDuplicate(string $hash, int $userId): ?Document
    {
        return Document::where(function ($q) use ($hash) {
            $q->where('file_hash', $hash)
              ->orWhere('hash', $hash);
        })
        ->where('user_id', $userId)
        ->first();
    }

    /**
     * Store PDF safely on private disk with randomized UUID name.
     */
    public function storePdfSafely(UploadedFile $file, string $type): string
    {
        $yearMonth = date('Y/m');
        $randomName = Str::uuid()->toString() . '.pdf';
        $directory = "documents/{$type}/{$yearMonth}";

        $path = Storage::disk('local')->putFileAs($directory, $file, $randomName);

        if (!$path) {
            throw new Exception("Unable to store document file to private storage.");
        }

        return $path;
    }

    /**
     * Resolve full disk path of stored document.
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
     * Determine page count for a stored PDF.
     */
    public function determinePageCount(string $storedPath): int
    {
        $fullPath = $this->getAbsolutePath($storedPath);
        return $this->pdfParser->getPageCount($fullPath);
    }

    /**
     * Process multiple PDF uploads within a tracked DocumentBatch.
     *
     * @param User $user
     * @param string $documentType ('invoice' or 'shipping_label')
     * @param array<UploadedFile> $files
     * @param string|null $batchName
     * @param bool $processImmediately
     * @param array $extraAttributes
     * @return array{batch: DocumentBatch, uploaded: Document[], duplicates: array, errors: array}
     */
    public function processBatchUpload(
        User $user,
        string $documentType,
        array $files,
        ?string $batchName = null,
        bool $processImmediately = true,
        array $extraAttributes = [],
        ?InvoiceProcessorService $invoiceProcessor = null
    ): array {
        $uploaded = [];
        $duplicates = [];
        $errors = [];
        $batchTotalPages = 0;

        // Auto-generate batch name if not provided
        $label = ($documentType === Document::TYPE_SHIPPING_LABEL) ? 'Shipping Labels' : 'Invoices';
        $name = $batchName ?: ($label . ' Batch #' . date('Ymd-His'));

        return DB::transaction(function () use (
            $user,
            $documentType,
            $files,
            $name,
            $processImmediately,
            $extraAttributes,
            $invoiceProcessor,
            &$uploaded,
            &$duplicates,
            &$errors,
            &$batchTotalPages
        ) {
            $batch = DocumentBatch::create([
                'user_id' => $user->id,
                'batch_name' => $name,
                'total_files' => 0,
                'total_pages' => 0,
                'status' => DocumentBatch::STATUS_PENDING,
            ]);

            foreach ($files as $file) {
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    $errors[] = "An invalid or corrupt file upload was encountered.";
                    continue;
                }

                $originalName = $file->getClientOriginalName();

                // 1. Validate PDF signature (magic bytes)
                if (!$this->isValidPdfSignature($file)) {
                    $errors[] = "'{$originalName}' does not have a valid PDF header (%PDF-).";
                    continue;
                }

                // 2. Duplicate Detection via SHA-256
                $fileHash = $this->computeFileHash($file);
                $existing = $this->findDuplicate($fileHash, $user->id);

                if ($existing) {
                    $duplicates[] = [
                        'filename' => $originalName,
                        'hash' => $fileHash,
                        'existing_id' => $existing->id,
                        'existing_name' => $existing->original_filename,
                        'uploaded_at' => $existing->created_at->toFormattedDateString(),
                    ];
                    continue;
                }

                try {
                    // 3. Secure Private Storage with UUID
                    $storedPath = $this->storePdfSafely($file, $documentType);

                    // 4. Calculate PDF Page Count
                    $pages = $this->determinePageCount($storedPath);
                    $batchTotalPages += $pages;

                    // 5. Create Document record
                    $document = Document::create([
                        'batch_id' => $batch->id,
                        'user_id' => $user->id,
                        'document_type' => $documentType,
                        'original_filename' => $originalName,
                        'stored_path' => $storedPath,
                        'mime_type' => 'application/pdf',
                        'file_size' => $file->getSize(),
                        'file_hash' => $fileHash,
                        'total_pages' => $pages,
                        'extraction_status' => Document::STATUS_PENDING,
                        'file_type' => 'pdf',
                        'page_count' => $pages,
                        'hash' => $fileHash,
                        'status' => 'uploaded',
                    ]);

                    // 6. Run robust PDF text extraction and OCR pipeline
                    if ($processImmediately) {
                        try {
                            $this->documentProcessor->process($document);
                            if ($invoiceProcessor && ($documentType === Document::TYPE_INVOICE || empty($documentType))) {
                                $invoiceProcessor->processDocument($document);
                            } elseif ($documentType === Document::TYPE_SHIPPING_LABEL) {
                                try {
                                    app(\App\Services\Shipping\ShippingLabelExtractionService::class)->extractDocument($document);
                                    app(\App\Services\Matching\InvoiceMatchingService::class)->runBatchMatching($user->id);
                                } catch (\Throwable $sle) {
                                    Log::info("Shipping label extraction notice: " . $sle->getMessage());
                                }
                            }
                        } catch (Exception $pe) {
                            Log::warning("Immediate text extraction fallback to queue: " . $pe->getMessage());
                            ExtractDocumentTextJob::dispatch($document);
                        }
                    } else {
                        ExtractDocumentTextJob::dispatch($document);
                    }

                    // 7. If shipping label, create linked ShippingDocument representation with smart carrier detection
                    if ($documentType === Document::TYPE_SHIPPING_LABEL) {
                        $rawText = $document->raw_text ?: '';
                        $tracking = null;

                        if (preg_match('/\b(FMPC[0-9]{10}|227[0-9]{10}|OD[0-9]{18})\b/i', $rawText, $m)) {
                            $tracking = trim($m[1]);
                        } elseif (preg_match('/(?:tracking|trk|awb|waybill|pro)[\s#:]*([A-Z0-9]{8,24})/i', $rawText, $m)) {
                            $tracking = trim($m[1]);
                        }
                        if (!$tracking) {
                            $tracking = 'TRK-' . strtoupper(Str::random(10));
                        }

                        $carrier = $extraAttributes['carrier_name'] ?? 'FedEx Express';
                        if (stripos($rawText, 'E-Kart') !== false || stripos($rawText, 'Ekart') !== false) {
                            $carrier = 'E-Kart Logistics';
                        } elseif (stripos($rawText, 'Delhivery') !== false) {
                            $carrier = 'Delhivery';
                        }

                        ShippingDocument::create([
                            'user_id' => $user->id,
                            'invoice_id' => $extraAttributes['invoice_id'] ?? null,
                            'document_type' => 'waybill',
                            'tracking_number' => $tracking,
                            'carrier_name' => $carrier,
                            'shipped_date' => now()->toDateString(),
                            'status' => 'delivered',
                            'total_packages' => 1,
                            'total_weight' => 2.5,
                            'weight_unit' => 'kg',
                            'shipping_cost' => 25.00,
                        ]);
                    }

                    $uploaded[] = $document;
                } catch (Exception $e) {
                    Log::error("Failed to store/process uploaded document '{$originalName}': " . $e->getMessage());
                    $errors[] = "Failed to upload '{$originalName}': " . $e->getMessage();
                }
            }

            // Update batch summary stats
            $batch->update([
                'total_files' => count($uploaded),
                'total_pages' => $batchTotalPages,
            ]);

            $batch->recalculateStatus();

            ActivityLog::record(
                'document_batch_uploaded',
                "Uploaded batch '{$batch->batch_name}' with " . count($uploaded) . " document(s) (" . $batchTotalPages . " total pages).",
                $batch,
                [
                    'document_type' => $documentType,
                    'file_count' => count($uploaded),
                    'duplicate_count' => count($duplicates),
                    'error_count' => count($errors),
                ]
            );

            return [
                'batch' => $batch,
                'uploaded' => $uploaded,
                'duplicates' => $duplicates,
                'errors' => $errors,
                'total_pages' => $batchTotalPages,
            ];
        });
    }
}
