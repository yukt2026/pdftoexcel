<?php

namespace App\Services\Invoice;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceExtractionResult;
use App\Models\InvoiceItem;
use App\Models\ShippingLabel;
use App\Models\Vendor;
use App\Services\AI\GeminiInvoiceService;
use App\Services\AI\InvoiceExtractionValidator;
use App\Services\Document\DocumentProcessingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceExtractionService
{
    public function __construct(
        protected GeminiInvoiceService $geminiService,
        protected InvoiceExtractionValidator $validator,
        protected DocumentProcessingService $documentProcessingService
    ) {}

    /**
     * Extract structured Indian GST invoice data from a Document and persist all records.
     * Supports single-page invoices, multi-page composite runs, and multi-order dispatch label sheets.
     *
     * @param Document $document
     * @param bool $forceReprocess
     * @return InvoiceExtractionResult
     * @throws Exception
     */
    public function extractAndPersist(Document $document, bool $forceReprocess = false): InvoiceExtractionResult
    {
        // 1. Ensure document has raw text extracted
        if (empty($document->raw_text) || $forceReprocess) {
            $document = $this->documentProcessingService->process($document);
        }

        $rawText = $document->raw_text ?? '';

        if (empty(trim($rawText))) {
            throw new Exception("Document {$document->id} contains no extractable text.");
        }

        // 2. Check if this document contains multiple pages with distinct invoices (e.g. batch packing slips)
        $pages = $document->pages()->orderBy('page_number')->get();
        if ($pages->count() > 1) {
            $pageInvoices = [];
            $seenInvoiceNumbers = [];

            foreach ($pages as $p) {
                $pText = $p->extracted_text ?? '';
                if (empty(trim($pText))) {
                    continue;
                }

                $pRes = $this->geminiService->extractIndianInvoice($pText);
                $invNum = $pRes['data']['invoice']['invoice_number'] ?? null;
                $items = $pRes['data']['items'] ?? $pRes['data']['products'] ?? [];

                if ($pRes['success'] && !empty($items) && $invNum) {
                    $pageInvoices[] = [
                        'page' => $p,
                        'result' => $pRes,
                        'invoice_number' => $invNum,
                    ];
                    $seenInvoiceNumbers[$invNum] = true;
                }
            }

            // If multiple distinct invoices were identified across pages, persist each page as an independent invoice
            if (count($seenInvoiceNumbers) > 1 && count($pageInvoices) > 1) {
                $firstResult = null;
                foreach ($pageInvoices as $entry) {
                    $res = $this->persistSingleInvoice(
                        $document,
                        $entry['page']->extracted_text,
                        $entry['result'],
                        $entry['page']->page_number
                    );
                    if (!$firstResult) {
                        $firstResult = $res;
                    }
                }

                if ($firstResult) {
                    return $firstResult;
                }
            }
        }

        // 3. Single Invoice Extraction (Standard single-page or consolidated document)
        $aiResult = $this->geminiService->extractIndianInvoice($rawText);
        return $this->persistSingleInvoice($document, $rawText, $aiResult);
    }

    /**
     * Persist extracted invoice data into MySQL records atomically.
     */
    public function persistSingleInvoice(
        Document $document,
        string $rawText,
        array $aiResult,
        ?int $pageNumber = null
    ): InvoiceExtractionResult {
        // Validate extracted data against Indian GST rules
        $validation = $this->validator->validate($aiResult['data'] ?? []);

        $invoiceData = $aiResult['data']['invoice'] ?? [];
        $productsData = $aiResult['data']['products'] ?? $aiResult['data']['items'] ?? [];
        $totalsData = $aiResult['data']['totals'] ?? [];
        $confidenceScores = $aiResult['data']['confidence_scores'] ?? [];
        $overallConfidence = (float) ($confidenceScores['overall'] ?? $aiResult['confidence'] ?? 90.0);

        $totalTaxable = (float) ($totalsData['total_taxable_value'] ?? 0.0);
        $totalCgst = (float) ($totalsData['total_cgst'] ?? 0.0);
        $totalSgst = (float) ($totalsData['total_sgst'] ?? 0.0);
        $totalIgst = (float) ($totalsData['total_igst'] ?? 0.0);
        $totalAmount = (float) ($totalsData['total_amount'] ?? 0.0);

        if ($totalAmount <= 0.0 && !empty($productsData)) {
            $totalTaxable = 0.0;
            $totalCgst = 0.0;
            $totalSgst = 0.0;
            $totalIgst = 0.0;
            $totalAmount = 0.0;
            foreach ($productsData as $prod) {
                $totalTaxable += (float) ($prod['taxable_value'] ?? 0.0);
                $totalCgst += (float) ($prod['cgst'] ?? 0.0);
                $totalSgst += (float) ($prod['sgst'] ?? 0.0);
                $totalIgst += (float) ($prod['igst'] ?? 0.0);
                $totalAmount += (float) ($prod['total'] ?? 0.0);
            }
        }

        // Determine extraction status
        $status = 'completed';
        if (!$aiResult['success'] && empty($productsData)) {
            $status = 'failed';
        } elseif (!($validation['is_valid'] ?? true) || $overallConfidence < 75.0) {
            $status = 'review_required';
        }

        return DB::transaction(function () use (
            $document,
            $rawText,
            $aiResult,
            $validation,
            $invoiceData,
            $productsData,
            $totalTaxable,
            $totalCgst,
            $totalSgst,
            $totalIgst,
            $totalAmount,
            $confidenceScores,
            $overallConfidence,
            $status,
            $pageNumber
        ) {
            // A. Find or create Vendor (Seller)
            $vendorId = null;
            if (!empty($invoiceData['seller_name'])) {
                $vendor = Vendor::firstOrCreate(
                    [
                        'name' => trim($invoiceData['seller_name']),
                    ],
                    [
                        'tax_number' => $invoiceData['seller_gstin'] ?? null,
                        'address' => $invoiceData['seller_address'] ?? null,
                    ]
                );
                $vendorId = $vendor->id;
            }

            // B. Find or create Customer (Buyer)
            $customerId = null;
            $buyerAddress = $invoiceData['buyer_address'] ?? $invoiceData['address'] ?? null;
            $buyerState = $invoiceData['buyer_state'] ?? $invoiceData['state'] ?? null;
            $placeOfSupply = $invoiceData['place_of_supply'] ?? $buyerState ?? null;

            if (!empty($invoiceData['buyer_name'])) {
                $customer = Customer::firstOrCreate(
                    [
                        'name' => trim($invoiceData['buyer_name']),
                    ],
                    [
                        'address' => $buyerAddress,
                        'state' => $buyerState,
                    ]
                );
                $customerId = $customer->id;
            }

            // C. Create or update Invoice record
            $parsedDate = $this->parseInvoiceDate($invoiceData['invoice_date'] ?? null);
            $invoiceNumber = $invoiceData['invoice_number'] ?? ('INV-' . strtoupper(uniqid()));

            // Lookup strategy: If document has invoice_id and pageNumber is null, maintain single record
            if ($document->invoice_id && $pageNumber === null) {
                $lookup = ['id' => $document->invoice_id];
            } else {
                $lookup = [
                    'document_id' => $document->id,
                    'invoice_number' => $invoiceNumber,
                ];
            }

            $invoice = Invoice::updateOrCreate(
                $lookup,
                [
                    'user_id' => $document->user_id,
                    'vendor_id' => $vendorId,
                    'customer_id' => $customerId,
                    'document_id' => $document->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $parsedDate,
                    'po_number' => $invoiceData['po_number'] ?? null,
                    'order_number' => $invoiceData['order_number'] ?? null,
                    'buyer_name' => $invoiceData['buyer_name'] ?? null,
                    'buyer_address' => $buyerAddress,
                    'buyer_state' => $buyerState,
                    'place_of_supply' => $placeOfSupply,
                    'currency' => 'INR',
                    'subtotal' => $totalTaxable,
                    'tax_amount' => $totalCgst + $totalSgst + $totalIgst,
                    'cgst_amount' => $totalCgst,
                    'sgst_amount' => $totalSgst,
                    'igst_amount' => $totalIgst,
                    'total_amount' => $totalAmount,
                    'processing_status' => $status === 'completed' ? 'extracted' : 'review_needed',
                    'extraction_method' => ($aiResult['is_fallback'] ?? false) ? 'rule_based' : 'gemini_ai',
                    'ai_confidence_score' => $overallConfidence,
                    'raw_extracted_text' => $rawText,
                    'ai_raw_response' => $aiResult['raw_response'] ?? null,
                    'meta_data' => [
                        'seller' => [
                            'name' => $invoiceData['seller_name'] ?? null,
                            'address' => $invoiceData['seller_address'] ?? null,
                            'gstin' => $invoiceData['seller_gstin'] ?? null,
                        ],
                        'is_fallback' => $aiResult['is_fallback'] ?? false,
                        'page_number' => $pageNumber,
                    ],
                ]
            );

            // D. Populate line items
            $invoice->items()->delete();
            foreach ($productsData as $product) {
                $itemTax = (float) (($product['cgst'] ?? 0.0) + ($product['sgst'] ?? 0.0) + ($product['igst'] ?? 0.0));

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $product['item_name'] ?? 'Product Item',
                    'quantity' => (float) ($product['quantity'] ?? 1.0),
                    'unit_price' => (float) ($product['item_rate'] ?? 0.0),
                    'item_rate' => (float) ($product['item_rate'] ?? 0.0),
                    'discount' => (float) ($product['discount'] ?? 0.0),
                    'taxable_value' => (float) ($product['taxable_value'] ?? 0.0),
                    'tax_amount' => $itemTax,
                    'cgst' => (float) ($product['cgst'] ?? 0.0),
                    'sgst' => (float) ($product['sgst'] ?? 0.0),
                    'igst' => (float) ($product['igst'] ?? 0.0),
                    'total_price' => (float) ($product['total'] ?? 0.0),
                    'is_extra_charge' => (bool) ($product['is_extra_charge'] ?? false),
                    'confidence_score' => (float) ($product['confidence'] ?? 90.0),
                ]);
            }

            // E. Link with extracted shipping label if on the same document/page
            $matchedLabel = ShippingLabel::where('document_id', $document->id)
                ->where(function ($q) use ($invoice, $pageNumber) {
                    if (!empty($invoice->order_number)) {
                        $q->orWhere('order_number', $invoice->order_number)
                          ->orWhere('order_number', 'like', $invoice->order_number . '%');
                    }
                    if (!empty($invoice->po_number)) {
                        $q->orWhere('order_number', $invoice->po_number)
                          ->orWhere('order_number', 'like', $invoice->po_number . '%');
                    }
                    if ($pageNumber !== null) {
                        $q->orWhere('page_number', $pageNumber);
                    }
                })
                ->first();

            if ($matchedLabel) {
                $shippingId = $matchedLabel->shipping_id ?: $matchedLabel->awb_number;
                $invoice->update([
                    'shipping_id' => $shippingId,
                    'matching_status' => 'matched',
                ]);
                $matchedLabel->update([
                    'invoice_id' => $invoice->id,
                    'status' => ShippingLabel::STATUS_MATCHED,
                ]);
            }

            // F. Create or update InvoiceExtractionResult record
            $extLookup = ($pageNumber === null && $document->invoice_id)
                ? ['document_id' => $document->id]
                : ['document_id' => $document->id, 'invoice_number' => $invoice->invoice_number];

            $extractionResult = InvoiceExtractionResult::updateOrCreate(
                $extLookup,
                [
                    'invoice_id' => $invoice->id,
                    'user_id' => $document->user_id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoiceData['invoice_date'] ?? null,
                    'po_number' => $invoiceData['po_number'] ?? null,
                    'order_number' => $invoiceData['order_number'] ?? null,
                    'buyer_name' => $invoiceData['buyer_name'] ?? null,
                    'buyer_address' => $buyerAddress,
                    'buyer_state' => $buyerState,
                    'place_of_supply' => $placeOfSupply,
                    'total_taxable_value' => $totalTaxable,
                    'total_cgst' => $totalCgst,
                    'total_sgst' => $totalSgst,
                    'total_igst' => $totalIgst,
                    'total_amount' => $totalAmount,
                    'extracted_data' => $aiResult['data'] ?? [],
                    'raw_text' => $rawText,
                    'ai_raw_response' => $aiResult['raw_response'] ?? null,
                    'ai_model' => $aiResult['ai_model'] ?? 'gemini-3.5-flash-lite',
                    'overall_confidence' => $overallConfidence,
                    'field_confidence_scores' => $confidenceScores,
                    'status' => $status,
                    'validation_errors' => !empty($validation['errors']) ? $validation['errors'] : null,
                ]
            );

            // G. Link primary invoice to document
            if (empty($document->invoice_id)) {
                $document->update([
                    'invoice_id' => $invoice->id,
                    'extraction_status' => Document::STATUS_COMPLETED,
                    'status' => 'completed',
                ]);
            } else {
                $document->update([
                    'extraction_status' => Document::STATUS_COMPLETED,
                    'status' => 'completed',
                ]);
            }

            // H. Record activity log
            ActivityLog::record(
                'invoice_extracted_ai',
                "AI extracted invoice #{$invoice->invoice_number} from '{$document->original_filename}' with {$overallConfidence}% confidence.",
                $invoice,
                [
                    'document_id' => $document->id,
                    'extraction_result_id' => $extractionResult->id,
                    'invoice_number' => $invoice->invoice_number,
                    'products_count' => count($productsData),
                    'is_fallback' => $aiResult['is_fallback'] ?? false,
                    'page_number' => $pageNumber,
                ]
            );

            return $extractionResult->load(['document', 'invoice.items']);
        });
    }

    /**
     * Safely parse various Indian invoice date formats to Y-m-d.
     *
     * @param string|null $dateStr
     * @return string|null
     */
    public function parseInvoiceDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);

        // DD.MM.YYYY or DD-MM-YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[\.\-\/](\d{1,2})[\.\-\/](\d{4})$/', $dateStr, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            return "{$year}-{$month}-{$day}";
        }

        // YYYY-MM-DD
        if (preg_match('/^(\d{4})[\.\-\/](\d{1,2})[\.\-\/](\d{1,2})$/', $dateStr, $matches)) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            return "{$year}-{$month}-{$day}";
        }

        try {
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
