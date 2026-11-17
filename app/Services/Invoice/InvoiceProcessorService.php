<?php

namespace App\Services\Invoice;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Vendor;
use App\Services\AI\GeminiAIService;
use App\Services\OCR\OcrService;
use App\Services\Pdf\PdfParserService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceProcessorService
{
    public function __construct(
        protected PdfParserService $pdfParser,
        protected OcrService $ocrService,
        protected GeminiAIService $geminiAI,
        protected ?InvoiceExtractionService $extractionService = null
    ) {
        $this->extractionService = $extractionService ?: app(InvoiceExtractionService::class);
    }

    /**
     * Process an uploaded document into structured invoice records.
     */
    public function processDocument(Document $document): Invoice
    {
        // 1. If document contains shipping labels (e.g. combined dispatch slip), extract them first
        try {
            app(\App\Services\Shipping\ShippingLabelExtractionService::class)->extractDocument($document);
        } catch (\Throwable $se) {
            Log::info("Shipping label extraction notice on doc #{$document->id}: " . $se->getMessage());
        }

        // 2. If document is an invoice and extractionService is available, use intelligent Indian GST extraction
        if ($this->extractionService && ($document->document_type === Document::TYPE_INVOICE || empty($document->document_type) || $document->document_type === 'invoice')) {
            $result = $this->extractionService->extractAndPersist($document);

            // 3. Automatically run matching engine to link extracted invoices with shipping labels
            try {
                app(\App\Services\Matching\InvoiceMatchingService::class)->runBatchMatching($document->user_id);
            } catch (\Throwable $me) {
                Log::info("Batch matching notice on user #{$document->user_id}: " . $me->getMessage());
            }

            if ($result->invoice) {
                return $result->invoice;
            }
        }

        $document->update([
            'status' => 'parsing',
            'extraction_status' => Document::STATUS_PROCESSING,
        ]);

        $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($document->stored_path);
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/' . $document->stored_path);
        }
        if (!file_exists($filePath)) {
            // Check public storage fallback if used
            $filePath = storage_path('app/public/' . $document->stored_path);
        }

        if (!file_exists($filePath)) {
            $document->update([
                'status' => 'failed',
                'error_message' => "File not found on disk: {$document->stored_path}",
            ]);
            throw new Exception("Document file missing on disk.");
        }

        try {
            // 1. Text Extraction
            $extractedText = '';
            $pageCount = 1;
            $extractionMethod = 'pdf_parser';

            if (in_array(strtolower($document->file_type), ['pdf'])) {
                $parseResult = $this->pdfParser->parseFile($filePath);
                $extractedText = $parseResult['text'];
                $pageCount = $parseResult['page_count'];

                // If scanned or no selectable text, attempt OCR
                if ($parseResult['is_scanned'] && $this->ocrService->isAvailable()) {
                    $ocrText = $this->ocrService->extractTextFromImage($filePath);
                    if (!empty($ocrText)) {
                        $extractedText = $ocrText;
                        $extractionMethod = 'ocr';
                    }
                }
            } elseif (in_array(strtolower($document->file_type), ['png', 'jpg', 'jpeg', 'image_png', 'image_jpg'])) {
                $extractionMethod = 'ocr';
                $extractedText = $this->ocrService->extractTextFromImage($filePath);
            }

            $document->update([
                'page_count' => $pageCount,
            ]);

            // 2. AI Extraction
            $aiData = [];
            $confidence = 85.0;

            if ($this->geminiAI->isConfigured() && !empty($extractedText)) {
                $extractionMethod = ($extractionMethod === 'ocr') ? 'hybrid' : 'gemini_ai';
                $aiData = $this->geminiAI->extractInvoiceFromText($extractedText);
                $confidence = $aiData['overall_confidence_score'] ?? 90.0;
            } else {
                // Rule-based fallback parsing when Gemini key is not configured or offline
                $aiData = $this->fallbackRegexExtraction($extractedText, $document->original_filename);
            }

            // 3. Database Persistence in Transaction
            return DB::transaction(function () use ($document, $aiData, $extractedText, $extractionMethod, $confidence) {
                // Find or create Vendor
                $vendor = null;
                if (!empty($aiData['vendor']['name'])) {
                    $vendor = Vendor::firstOrCreate(
                        ['name' => trim($aiData['vendor']['name'])],
                        [
                            'tax_number' => $aiData['vendor']['tax_number'] ?? null,
                            'email' => $aiData['vendor']['email'] ?? null,
                            'phone' => $aiData['vendor']['phone'] ?? null,
                            'address' => $aiData['vendor']['address'] ?? null,
                            'city' => $aiData['vendor']['city'] ?? null,
                            'state' => $aiData['vendor']['state'] ?? null,
                            'country' => $aiData['vendor']['country'] ?? null,
                            'postal_code' => $aiData['vendor']['postal_code'] ?? null,
                        ]
                    );
                }

                // Find or create Customer
                $customer = null;
                if (!empty($aiData['customer']['name'])) {
                    $customer = Customer::firstOrCreate(
                        ['name' => trim($aiData['customer']['name'])],
                        [
                            'tax_number' => $aiData['customer']['tax_number'] ?? null,
                            'email' => $aiData['customer']['email'] ?? null,
                            'phone' => $aiData['customer']['phone'] ?? null,
                            'address' => $aiData['customer']['address'] ?? null,
                            'city' => $aiData['customer']['city'] ?? null,
                            'state' => $aiData['customer']['state'] ?? null,
                            'country' => $aiData['customer']['country'] ?? null,
                            'postal_code' => $aiData['customer']['postal_code'] ?? null,
                        ]
                    );
                }

                // Create or update Invoice
                $invoice = Invoice::create([
                    'user_id' => $document->user_id,
                    'vendor_id' => $vendor?->id,
                    'customer_id' => $customer?->id,
                    'invoice_number' => $aiData['invoice_number'] ?? ('INV-' . strtoupper(substr(md5(uniqid()), 0, 8))),
                    'invoice_date' => !empty($aiData['invoice_date']) ? date('Y-m-d', strtotime($aiData['invoice_date'])) : now()->toDateString(),
                    'due_date' => !empty($aiData['due_date']) ? date('Y-m-d', strtotime($aiData['due_date'])) : now()->addDays(30)->toDateString(),
                    'po_number' => $aiData['po_number'] ?? null,
                    'currency' => $aiData['currency'] ?? 'INR',
                    'subtotal' => $aiData['totals']['subtotal'] ?? 0.0,
                    'tax_amount' => $aiData['totals']['tax_amount'] ?? 0.0,
                    'discount_amount' => $aiData['totals']['discount_amount'] ?? 0.0,
                    'shipping_amount' => $aiData['totals']['shipping_amount'] ?? 0.0,
                    'total_amount' => $aiData['totals']['total_amount'] ?? 0.0,
                    'amount_paid' => $aiData['totals']['amount_paid'] ?? 0.0,
                    'balance_due' => $aiData['totals']['balance_due'] ?? ($aiData['totals']['total_amount'] ?? 0.0),
                    'payment_terms' => $aiData['payment_terms'] ?? 'Net 30',
                    'payment_status' => 'unpaid',
                    'processing_status' => 'extracted',
                    'matching_status' => 'unmatched',
                    'extraction_method' => $extractionMethod,
                    'ai_confidence_score' => $confidence,
                    'notes' => $aiData['notes'] ?? null,
                    'raw_extracted_text' => $extractedText,
                    'ai_raw_response' => $aiData,
                ]);

                // Create line items
                if (!empty($aiData['items']) && is_array($aiData['items'])) {
                    foreach ($aiData['items'] as $item) {
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'item_code' => $item['item_code'] ?? null,
                            'description' => $item['description'] ?? 'Extracted Item',
                            'quantity' => $item['quantity'] ?? 1,
                            'unit' => $item['unit'] ?? 'unit',
                            'unit_price' => $item['unit_price'] ?? 0.0,
                            'discount' => $item['discount'] ?? 0.0,
                            'tax_rate' => $item['tax_rate'] ?? 0.0,
                            'tax_amount' => $item['tax_amount'] ?? 0.0,
                            'total_price' => $item['total_price'] ?? 0.0,
                            'category' => $item['category'] ?? null,
                            'confidence_score' => $item['confidence_score'] ?? $confidence,
                        ]);
                    }
                }

                // Associate Document with Invoice
                $document->update([
                    'invoice_id' => $invoice->id,
                    'status' => 'completed',
                    'extraction_status' => Document::STATUS_COMPLETED,
                ]);

                $document->batch?->recalculateStatus();

                // Audit log
                ActivityLog::record(
                    'invoice_extracted',
                    "Invoice {$invoice->invoice_number} successfully extracted via {$extractionMethod}",
                    $invoice,
                    ['confidence' => $confidence, 'document_id' => $document->id]
                );

                return $invoice;
            });
        } catch (Exception $e) {
            $document->update([
                'status' => 'failed',
                'extraction_status' => Document::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $document->batch?->recalculateStatus();
            Log::error('Invoice extraction failed: ' . $e->getMessage(), ['document_id' => $document->id]);
            throw $e;
        }
    }

    /**
     * Fallback Regex parser for when AI API is unavailable.
     */
    protected function fallbackRegexExtraction(string $text, string $filename): array
    {
        $invoiceNumber = null;
        if (preg_match('/(?:invoice|inv|bill)[\s#:\.\-]+([a-z0-9\-]+)/i', $text, $matches)) {
            $invoiceNumber = trim($matches[1]);
        }

        $totalAmount = 0.0;
        if (preg_match('/(?:total|amount\s+due|grand\s+total)[\s:$\€\£]*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
            $totalAmount = (float) str_replace(',', '', $matches[1]);
        }

        return [
            'invoice_number' => $invoiceNumber ?: ('INV-' . strtoupper(substr(md5($filename . time()), 0, 8))),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'po_number' => null,
            'currency' => 'INR',
            'vendor' => ['name' => 'Auto-Detected Supplier'],
            'customer' => ['name' => 'Standard Client'],
            'items' => [
                [
                    'item_code' => 'GEN-01',
                    'description' => 'Document Line Items Summary (' . $filename . ')',
                    'quantity' => 1,
                    'unit' => 'lot',
                    'unit_price' => $totalAmount,
                    'discount' => 0.0,
                    'tax_rate' => 0.0,
                    'tax_amount' => 0.0,
                    'total_price' => $totalAmount,
                    'confidence_score' => 70.0,
                ]
            ],
            'totals' => [
                'subtotal' => $totalAmount,
                'tax_amount' => 0.0,
                'discount_amount' => 0.0,
                'shipping_amount' => 0.0,
                'total_amount' => $totalAmount,
                'amount_paid' => 0.0,
                'balance_due' => $totalAmount,
            ],
            'payment_terms' => 'Net 30',
            'overall_confidence_score' => 70.0,
            'notes' => 'Extracted via fallback parser.',
        ];
    }
}
