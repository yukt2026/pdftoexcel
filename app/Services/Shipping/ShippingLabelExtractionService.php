<?php

namespace App\Services\Shipping;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentPage;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\ShippingLabel;
use App\Repositories\ShippingLabelRepository;
use App\Services\Document\DocumentProcessingService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingLabelExtractionService
{
    protected ?string $geminiApiKey;
    protected string $geminiModel;

    public function __construct(
        protected ShippingLabelRepository $repository,
        protected DocumentProcessingService $documentProcessingService
    ) {
        $this->geminiApiKey = Setting::get('gemini_api_key')
            ?: config('services.gemini.api_key')
            ?: env('GEMINI_API_KEY');

        $this->geminiModel = Setting::get('gemini_model')
            ?: config('services.gemini.model')
            ?: env('GEMINI_MODEL', 'gemini-3.5-flash-lite');
    }

    /**
     * Extract shipping labels from a Document across all its pages.
     *
     * @param Document $document
     * @param bool $forceReprocess
     * @return \Illuminate\Support\Collection<int, ShippingLabel>
     * @throws Exception
     */
    public function extractDocument(Document $document, bool $forceReprocess = false): \Illuminate\Support\Collection
    {
        // 1. Ensure document text is parsed and segmented into pages
        if (empty($document->raw_text) || $document->pages()->count() === 0 || $forceReprocess) {
            $document = $this->documentProcessingService->process($document);
        }

        $pages = $document->pages()->orderBy('page_number')->get();

        if ($pages->isEmpty() && !empty($document->raw_text)) {
            // Fallback for single stream text
            $pages = collect([
                (object) [
                    'id' => null,
                    'page_number' => 1,
                    'extracted_text' => $document->raw_text,
                ]
            ]);
        }

        $createdLabels = collect();

        DB::transaction(function () use ($document, $pages, &$createdLabels) {
            foreach ($pages as $page) {
                $pageText = $page->extracted_text ?? '';
                if (empty(trim($pageText))) {
                    continue;
                }

                $pageNumber = $page->page_number ?? 1;
                $pageId = $page->id ?? null;

                $extracted = $this->extractPage($pageText, $pageNumber);

                // Associate with existing Invoice if order_number matches
                $invoiceId = null;
                $status = ShippingLabel::STATUS_EXTRACTED;
                if (!empty($extracted['order_number'])) {
                    $matchedInvoice = Invoice::where('user_id', $document->user_id)
                        ->where(function ($q) use ($extracted) {
                            $q->where('order_number', $extracted['order_number'])
                              ->orWhere('po_number', $extracted['order_number']);
                        })
                        ->first();

                    if ($matchedInvoice) {
                        $invoiceId = $matchedInvoice->id;
                    }
                }

                // If invoice already linked to document, associate it
                if (!$invoiceId && $document->invoice_id) {
                    $invoiceId = $document->invoice_id;
                }

                $label = ShippingLabel::updateOrCreate(
                    [
                        'document_id' => $document->id,
                        'page_number' => $pageNumber,
                    ],
                    [
                        'user_id' => $document->user_id,
                        'document_page_id' => $pageId,
                        'invoice_id' => $invoiceId,
                        'awb_number' => $extracted['awb_number'] ?? ('AWB-' . strtoupper(uniqid())),
                        'original_awb_number' => $extracted['awb_number'] ?? null,
                        'order_number' => $extracted['order_number'] ?? null,
                        'buyer_name' => $extracted['buyer_name'] ?? null,
                        'shipping_address' => $extracted['shipping_address'] ?? null,
                        'state' => $extracted['state'] ?? null,
                        'pincode' => $extracted['pincode'] ?? null,
                        'courier_name' => $extracted['courier_name'] ?? 'Standard Courier',
                        'marketplace_name' => $extracted['marketplace_name'] ?? null,
                        'label_format' => $extracted['label_format'] ?? ShippingLabel::FORMAT_STANDARD_COURIER,
                        'extraction_method' => $extracted['extraction_method'] ?? 'deterministic',
                        'confidence_score' => $extracted['confidence_score'] ?? 90.00,
                        'field_confidence_scores' => $extracted['field_confidence_scores'] ?? null,
                        'raw_text' => $pageText,
                        'raw_data' => $extracted,
                        'status' => $status,
                    ]
                );

                $createdLabels->push($label);
            }

            $document->update([
                'extraction_status' => Document::STATUS_COMPLETED,
            ]);

            ActivityLog::record(
                'shipping_labels_extracted',
                "Extracted " . $createdLabels->count() . " shipping label(s) from '{$document->original_filename}'.",
                $document,
                ['count' => $createdLabels->count()]
            );
        });

        return $createdLabels;
    }

    /**
     * Extract fields for a single shipping label page text.
     * Uses deterministic parsing first; escalates to Gemini for ambiguous/low-confidence layouts.
     */
    public function extractPage(string $pageText, int $pageNumber = 1): array
    {
        // 1. Deterministic Parsing
        $deterministic = $this->parseDeterministic($pageText, $pageNumber);

        // If deterministic parsing found an AWB and has high confidence, return directly
        if (!empty($deterministic['awb_number']) && $deterministic['confidence_score'] >= 85.0) {
            return $deterministic;
        }

        // 2. Escalation to Gemini AI for complex / ambiguous layouts if configured
        if (!empty($this->geminiApiKey)) {
            $aiData = $this->extractViaGemini($pageText);
            if ($aiData && !empty($aiData['awb_number'])) {
                $aiData['extraction_method'] = 'gemini_ai';
                $aiData['page_number'] = $pageNumber;
                return $aiData;
            }
        }

        // Return best-effort deterministic result
        return $deterministic;
    }

    /**
     * Deterministic regex parsing for known shipping formats (Flipkart E-Kart, Delhivery, Bluedart, etc.).
     */
    public function parseDeterministic(string $text, int $pageNumber = 1): array
    {
        $awb = null;
        $orderNumber = null;
        $buyerName = null;
        $shippingAddress = null;
        $state = null;
        $pincode = null;
        $courierName = null;
        $marketplaceName = null;
        $format = ShippingLabel::FORMAT_STANDARD_COURIER;
        $confidence = 75.0;

        $isFlipkart = (stripos($text, 'E-Kart') !== false || stripos($text, 'EKART') !== false || stripos($text, 'FMPC') !== false || stripos($text, 'Flipkart') !== false);
        $isDelhivery = (stripos($text, 'Delhivery') !== false || stripos($text, 'Destination Code') !== false || preg_match('/\b(14[0-9]{14})\b/', $text));
        $isBluedart = (stripos($text, 'Blue Dart') !== false || stripos($text, 'BLUEDART') !== false);

        if ($isFlipkart) {
            $format = ShippingLabel::FORMAT_FLIPKART_EKART;
            $courierName = 'E-Kart Logistics';
            $marketplaceName = 'Flipkart';

            // 1. AWB / Shipping ID
            if (preg_match('/\b(FMPC[0-9]{10})\b/', $text, $m)) {
                $awb = $m[1];
            } elseif (preg_match('/AWB\s*No\.?\s*([A-Za-z0-9]+)/i', $text, $m)) {
                $awb = trim($m[1]);
            }

            // 2. Order Number
            if (preg_match('/\b(OD[0-9]{18})\b/', $text, $m)) {
                $orderNumber = $m[1];
            } elseif (preg_match('/(?:Order\s*Id|Order\s*No)[\s#:]*([0-9A-Za-z_-]{10,24})/i', $text, $m)) {
                $orderNumber = trim($m[1]);
            }

            // 3. Buyer Name & Address
            if (preg_match('/Shipping\/Customer\s*address:\s*\n*(?:Name:\s*)?([^\n,]+),?\s*\n+(.+?)(?:Not\s*for\s*resale|Sold\s*By|Billing\s*Address)/is', $text, $m)) {
                $buyerName = trim($m[1]);
                $rawAddr = trim(preg_replace('/\s+/', ' ', $m[2]));
                $shippingAddress = $rawAddr;

                if (preg_match('/\b([1-9][0-9]{5})\b/', $rawAddr, $pinM)) {
                    $pincode = $pinM[1];
                }

                if (preg_match('/IN-([A-Z]{2})\b/i', $rawAddr, $stM)) {
                    $state = $this->resolveStateCode($stM[1]);
                }
            }
        } elseif ($isDelhivery) {
            $format = ShippingLabel::FORMAT_DELHIVERY;
            $courierName = 'Delhivery';
            $marketplaceName = 'Meesho'; // Typical e-commerce seller shipping label format

            // 1. AWB: Delhivery waybill is 14-16 digits right after Return Code, NOT the return code
            if (preg_match('/Return\s*Code\s*\n+[0-9]+,[0-9]+\s*\n+([0-9]{14,16})\b/i', $text, $m)) {
                $awb = trim($m[1]);
            } elseif (preg_match('/\b(14[0-9]{14})\b/', $text, $m)) {
                $awb = $m[1];
            }

            // 2. Order Number
            if (preg_match('/(?:Order\s*No\.?|Purchase\s*Order\s*No\.?)[\s#:\n]*([0-9]{16,20}(?:_[0-9]+)?)\b/i', $text, $m)) {
                $orderNumber = trim($m[1]);
            }

            // 3. Buyer Name & Address (DO NOT use seller address after 'If undelivered, return to:')
            if (preg_match('/Customer\s*Address\s*\n+([A-Za-z\s]+?)\n+(.+?)(?:If\s*undelivered|\nPrepaid|\nDelhivery)/is', $text, $m)) {
                $buyerName = trim(ucwords(strtolower(trim($m[1]))));
                $rawAddr = trim(preg_replace('/\s+/', ' ', $m[2]));
                $shippingAddress = $rawAddr;

                if (preg_match('/\b([1-9][0-9]{5})\b/', $rawAddr, $pinM)) {
                    $pincode = $pinM[1];
                }

                if (preg_match('/,\s*([A-Za-z\s]+),\s*[0-9]{6}/', $rawAddr, $stM)) {
                    $state = trim($stM[1]);
                }
            }

            if (empty($state) && preg_match('/Place\s*of\s*Supply\s*:\s*([A-Za-z\s]+?)(?:\n|Sold\s*by|$)/i', $text, $stM)) {
                $state = trim($stM[1]);
            }
        } elseif ($isBluedart) {
            $format = 'bluedart';
            $courierName = 'Blue Dart';
            if (preg_match('/\b([0-9]{9,11})\b/', $text, $m)) {
                $awb = $m[1];
            }
        }

        // Generic courier fallback parsing
        if (empty($awb)) {
            if (preg_match('/(?:AWB|Tracking\s*(?:No|#)|Waybill)[\s#:]*([A-Za-z0-9]{8,24})/i', $text, $m)) {
                $awb = trim($m[1]);
            }
        }

        if (empty($orderNumber)) {
            if (preg_match('/(?:Order\s*(?:No|ID|#)|Ref\s*#)[\s#:]*([A-Za-z0-9_-]{8,24})/i', $text, $m)) {
                $orderNumber = trim($m[1]);
            }
        }

        if (empty($pincode) && preg_match('/\b([1-9][0-9]{5})\b/', $text, $m)) {
            $pincode = $m[1];
        }

        if (empty($courierName)) {
            if (preg_match('/(?:Courier|Carrier|Shipped\s*Via)[\s#:]*([A-Za-z\s]+?)(?:\n|,|$)/i', $text, $m)) {
                $courierName = trim($m[1]);
            } else {
                $courierName = 'Standard Courier';
            }
        }

        if ($awb && $orderNumber && $buyerName) {
            $confidence = 95.0;
        } elseif ($awb) {
            $confidence = 85.0;
        }

        return [
            'page_number' => $pageNumber,
            'label_format' => $format,
            'courier_name' => $courierName,
            'marketplace_name' => $marketplaceName,
            'awb_number' => $awb,
            'original_awb_number' => $awb,
            'order_number' => $orderNumber,
            'buyer_name' => $buyerName,
            'shipping_address' => $shippingAddress,
            'state' => $state,
            'pincode' => $pincode,
            'extraction_method' => 'deterministic',
            'confidence_score' => $confidence,
            'field_confidence_scores' => [
                'awb_number' => $awb ? 95.0 : 40.0,
                'order_number' => $orderNumber ? 95.0 : 50.0,
                'buyer_name' => $buyerName ? 95.0 : 50.0,
                'shipping_address' => $shippingAddress ? 90.0 : 50.0,
                'state' => $state ? 90.0 : 50.0,
                'pincode' => $pincode ? 95.0 : 50.0,
                'courier_name' => 90.0,
                'marketplace_name' => 85.0,
            ],
        ];
    }

    /**
     * Gemini AI extraction fallback for complex, distorted or unstructured shipping labels.
     */
    public function extractViaGemini(string $text): ?array
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->geminiModel}:generateContent?key={$this->geminiApiKey}";

            $prompt = <<<PROMPT
You are an expert shipping logistics document parser.
Analyze this shipping label text and extract the exact fields in JSON format:

REQUIRED FIELDS:
1. awb_number: The primary Air Waybill (AWB) or tracking number.
   - For Delhivery, extract the 14-16 digit waybill (e.g. 1490843814423655). NEVER extract return codes or destination codes!
   - For E-Kart, extract the FMPC tracking code (e.g. FMPC6756481216).
2. order_number: Order Number or Purchase Order No.
3. buyer_name: Recipient / Customer name. NEVER use seller/return name!
4. shipping_address: Complete customer shipping delivery address. NEVER use seller address!
5. state: Destination state (e.g. Uttar Pradesh, Chandigarh, Odisha, Assam).
6. pincode: 6-digit destination postal pincode.
7. courier_name: Courier company name (e.g. E-Kart Logistics, Delhivery, Blue Dart, FedEx).
8. marketplace_name: Marketplace or platform (e.g. Flipkart, Meesho, Amazon, Shopify).
9. label_format: 'flipkart_ekart', 'delhivery', or 'standard_courier'.

RETURN ONLY JSON matching this structure:
{
  "awb_number": "string or null",
  "order_number": "string or null",
  "buyer_name": "string or null",
  "shipping_address": "string or null",
  "state": "string or null",
  "pincode": "string or null",
  "courier_name": "string or null",
  "marketplace_name": "string or null",
  "label_format": "string",
  "confidence_score": 95.0
}

TEXT:
{$text}
PROMPT;

            $response = Http::timeout(25)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($content) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning("Gemini shipping label extraction failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Resolve state code abbreviations (e.g. IN-UP to Uttar Pradesh).
     */
    protected function resolveStateCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $map = [
            'UP' => 'Uttar Pradesh', 'AS' => 'Assam', 'DL' => 'Delhi',
            'PB' => 'Punjab', 'HR' => 'Haryana', 'MH' => 'Maharashtra',
            'KA' => 'Karnataka', 'TN' => 'Tamil Nadu', 'WB' => 'West Bengal',
            'GJ' => 'Gujarat', 'RJ' => 'Rajasthan', 'OD' => 'Odisha',
            'MP' => 'Madhya Pradesh', 'BR' => 'Bihar', 'CH' => 'Chandigarh',
            'KL' => 'Kerala', 'TS' => 'Telangana', 'AP' => 'Andhra Pradesh',
            'UK' => 'Uttarakhand', 'JH' => 'Jharkhand', 'GA' => 'Goa',
        ];

        return $map[$code] ?? ('IN-' . $code);
    }
}
