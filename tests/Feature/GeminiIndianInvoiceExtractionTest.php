<?php

namespace Tests\Feature;

use App\Jobs\InvoiceExtractionJob;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\Invoice;
use App\Models\InvoiceExtractionResult;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\AI\GeminiInvoiceService;
use App\Services\AI\InvoiceExtractionValidator;
use App\Services\Document\DocumentProcessingService;
use App\Services\Invoice\InvoiceExtractionService;
use App\Services\Pdf\PdfTextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeminiIndianInvoiceExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $flipkartPdfPath;
    protected string $dreestiPdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->flipkartPdfPath = base_path('tests/Fixtures/flipkart_invoice_and_ekart_label.pdf');
        $this->dreestiPdfPath = base_path('tests/Fixtures/dreesti_delhivery_shipping_labels.pdf');
    }

    /**
     * Test extraction of all required invoice-level and product-level fields on Flipkart format.
     */
    public function test_gemini_service_extracts_flipkart_invoice_fields_accurately(): void
    {
        $extractor = app(PdfTextExtractionService::class);
        $extracted = $extractor->extractText($this->flipkartPdfPath);
        $this->assertGreaterThan(0, $extracted['page_count']);

        $gemini = app(GeminiInvoiceService::class);
        $result = $gemini->extractIndianInvoice($extracted['pages'][0]['text']);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['data']);

        $inv = $result['data']['invoice'];
        // 7 Invoice Level Fields
        $this->assertNotNull($inv['invoice_date']);
        $this->assertEquals('NUBFYP0270000004', $inv['invoice_number']);
        $this->assertEquals('OD439142473682225100', $inv['order_number']);
        $this->assertEquals('Sangeeta Soni', $inv['buyer_name']);
        $this->assertStringContainsString('Maharajganj', $inv['address']);
        $this->assertStringContainsString('Uttar Pradesh', $inv['state']);

        // 8 Product Level Fields
        $items = $result['data']['items'] ?? $result['data']['products'];
        $this->assertCount(2, $items); // Main Product + Handling Fee

        // Product 1
        $p1 = $items[0];
        $this->assertStringContainsString('VELFORA', $p1['item_name']);
        $this->assertEquals(353.00, $p1['item_rate']); // Gross Amount
        $this->assertEquals(0.00, $p1['discount']);
        $this->assertEquals(342.72, $p1['taxable_value']);
        $this->assertEquals(0.00, $p1['cgst']);
        $this->assertEquals(0.00, $p1['sgst']);
        $this->assertEquals(10.28, $p1['igst']);
        $this->assertEquals(353.00, $p1['total']);
        $this->assertFalse($p1['is_extra_charge']);

        // Product 2 (Handling Fee extracted separately)
        $p2 = $items[1];
        $this->assertEquals('Handling Fee', $p2['item_name']);
        $this->assertTrue($p2['is_extra_charge']);
        $this->assertEquals(0.00, $p2['total']);

        // Confidence Scores
        $this->assertArrayHasKey('confidence_scores', $result['data']);
        $this->assertGreaterThanOrEqual(80.0, $result['confidence']);
    }

    /**
     * Test extraction of all required invoice-level and product-level fields on DREESTI format.
     */
    public function test_gemini_service_extracts_dreesti_invoice_fields_accurately(): void
    {
        $extractor = app(PdfTextExtractionService::class);
        $extracted = $extractor->extractText($this->dreestiPdfPath);
        $this->assertGreaterThan(0, $extracted['page_count']);

        $gemini = app(GeminiInvoiceService::class);
        $result = $gemini->extractIndianInvoice($extracted['pages'][0]['text']);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['data']);

        $inv = $result['data']['invoice'];
        // 7 Invoice Level Fields
        $this->assertNotNull($inv['invoice_date']);
        $this->assertEquals('gpzdk27301', $inv['invoice_number']);
        $this->assertEquals('351974314617931328', $inv['po_number']);
        $this->assertEquals('351974314617931328', $inv['order_number']);
        $this->assertEquals('Deep Singh', $inv['buyer_name']);
        $this->assertStringContainsString('hallo majra', $inv['address']);
        $this->assertEquals('Chandigarh', $inv['state']);

        // 8 Product Level Fields
        $items = $result['data']['items'] ?? $result['data']['products'];
        $this->assertCount(2, $items); // Main Product + Other Charges

        // Product 1
        $p1 = $items[0];
        $this->assertStringContainsString('Mini RC Helicopter', $p1['item_name']);
        $this->assertEquals(493.03, $p1['item_rate']); // Gross Amount
        $this->assertEquals(199.00, $p1['discount']);
        $this->assertEquals(249.18, $p1['taxable_value']);
        $this->assertEquals(0.00, $p1['cgst']);
        $this->assertEquals(0.00, $p1['sgst']);
        $this->assertEquals(44.85, $p1['igst']);
        $this->assertEquals(294.03, $p1['total']);
        $this->assertFalse($p1['is_extra_charge']);

        // Product 2 (Other Charges extracted separately)
        $p2 = $items[1];
        $this->assertEquals('Other Charges', $p2['item_name']);
        $this->assertEquals(28.00, $p2['item_rate']);
        $this->assertEquals(0.00, $p2['discount']);
        $this->assertEquals(23.73, $p2['taxable_value']);
        $this->assertEquals(4.27, $p2['igst']);
        $this->assertEquals(28.00, $p2['total']);
        $this->assertTrue($p2['is_extra_charge']);
    }

    /**
     * Test InvoiceExtractionService end-to-end database persistence for Flipkart invoice.
     */
    public function test_invoice_extraction_service_persists_to_database_records(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Test Batch',
            'total_files' => 1,
            'total_pages' => 2,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $document = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'flipkart_test.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->flipkartPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $service = app(InvoiceExtractionService::class);
        $result = $service->extractAndPersist($document);

        $this->assertInstanceOf(InvoiceExtractionResult::class, $result);
        $this->assertEquals('NUBFYP0270000004', $result->invoice_number);
        $this->assertEquals('OD439142473682225100', $result->order_number);
        $this->assertEquals('Sangeeta Soni', $result->buyer_name);
        $this->assertEquals('Uttar Pradesh', $result->buyer_state);
        $this->assertEquals('completed', $result->status);

        // Verify Database Records
        $this->assertDatabaseHas('invoice_extraction_results', [
            'document_id' => $document->id,
            'invoice_number' => 'NUBFYP0270000004',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $result->invoice_id,
            'invoice_number' => 'NUBFYP0270000004',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'buyer_state' => 'Uttar Pradesh',
            'igst_amount' => 10.28,
            'total_amount' => 353.00,
        ]);

        // Verify Line Items
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $result->invoice_id,
            'item_rate' => 353.00,
            'taxable_value' => 342.72,
            'igst' => 10.28,
            'total_price' => 353.00,
            'is_extra_charge' => false,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $result->invoice_id,
            'description' => 'Handling Fee',
            'is_extra_charge' => true,
        ]);

        // Verify Document Updated
        $document->refresh();
        $this->assertEquals(Document::STATUS_COMPLETED, $document->extraction_status);
        $this->assertEquals($result->invoice_id, $document->invoice_id);
    }

    /**
     * Test InvoiceExtractionService persistence with DREESTI invoice fixture.
     */
    public function test_invoice_extraction_service_with_dreesti_fixture_persists_items_and_other_charges(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'DREESTI Batch',
            'total_files' => 1,
            'total_pages' => 2,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $document = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'dreesti_test.pdf',
            'stored_path' => 'tests/Fixtures/dreesti_delhivery_shipping_labels.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->dreestiPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $service = app(InvoiceExtractionService::class);
        $result = $service->extractAndPersist($document);

        $this->assertInstanceOf(InvoiceExtractionResult::class, $result);
        $this->assertEquals('gpzdk27301', $result->invoice_number);
        $this->assertEquals('351974314617931328', $result->po_number);
        $this->assertEquals('351974314617931328', $result->order_number);
        $this->assertEquals('Deep Singh', $result->buyer_name);
        $this->assertEquals('Chandigarh', $result->buyer_state);

        // Verify Database Records
        $invoice = Invoice::with('items')->find($result->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertEquals('gpzdk27301', $invoice->invoice_number);
        $this->assertEquals(322.03, (float) $invoice->total_amount);
        $this->assertEquals(49.12, (float) $invoice->igst_amount);

        // Items count
        $this->assertCount(2, $invoice->items);

        $mainItem = $invoice->items->where('is_extra_charge', false)->first();
        $this->assertNotNull($mainItem);
        $this->assertEquals(493.03, (float) $mainItem->item_rate);
        $this->assertEquals(199.00, (float) $mainItem->discount);
        $this->assertEquals(249.18, (float) $mainItem->taxable_value);
        $this->assertEquals(44.85, (float) $mainItem->igst);
        $this->assertEquals(294.03, (float) $mainItem->total_price);

        $otherChargeItem = $invoice->items->where('is_extra_charge', true)->first();
        $this->assertNotNull($otherChargeItem);
        $this->assertEquals('Other Charges', $otherChargeItem->description);
        $this->assertEquals(28.00, (float) $otherChargeItem->item_rate);
        $this->assertEquals(23.73, (float) $otherChargeItem->taxable_value);
        $this->assertEquals(4.27, (float) $otherChargeItem->igst);
        $this->assertEquals(28.00, (float) $otherChargeItem->total_price);
    }

    /**
     * Test InvoiceExtractionJob queue processing.
     */
    public function test_invoice_extraction_job_runs_asynchronously(): void
    {
        Queue::fake();

        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'async_test.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'total_pages' => 1,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        InvoiceExtractionJob::dispatch($document);

        Queue::assertPushed(InvoiceExtractionJob::class, function ($job) use ($document) {
            return $job->document->id === $document->id;
        });
    }

    /**
     * Test missing fields are null and not fabricated.
     */
    public function test_missing_fields_are_null_not_fabricated(): void
    {
        $rawText = "Random receipt with no buyer or PO. Invoice No: INV9999. Date: 15-11-2026. Item: Generic Pen Rs. 50.";

        $gemini = app(GeminiInvoiceService::class);
        $result = $gemini->extractIndianInvoice($rawText);

        $this->assertTrue($result['success']);
        $inv = $result['data']['invoice'];

        $this->assertNull($inv['po_number']);
        $this->assertNull($inv['buyer_name']);
        $this->assertNull($inv['address']);
    }

    /**
     * Test validator catches invalid payloads.
     */
    public function test_validator_detects_empty_or_invalid_payload(): void
    {
        $validator = app(InvoiceExtractionValidator::class);
        $validation = $validator->validate([]);

        $this->assertFalse($validation['is_valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /**
     * Test Gemini API remote call with JSON parsing when API key is configured.
     */
    public function test_gemini_service_calls_google_api_with_configured_api_key_and_parses_json_response(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'invoice' => [
                                            'invoice_date' => '2026-11-13',
                                            'invoice_number' => 'GEMINI-INV-9900',
                                            'po_number' => 'PO-8888',
                                            'order_number' => 'OD-7777',
                                            'buyer_name' => 'Rahul Sharma',
                                            'address' => 'Sector 17, Chandigarh, 160017',
                                            'state' => 'Chandigarh',
                                        ],
                                        'seller' => [
                                            'name' => 'GATIMAAN ENTERPRISES',
                                            'gstin' => '03EWIPM1640G1ZS',
                                            'pan' => 'EWIPM1640G',
                                            'address' => 'Ludhiana, Punjab',
                                            'state' => 'Punjab',
                                        ],
                                        'items' => [
                                            [
                                                'item_name' => 'Silver Jewellery Set',
                                                'item_code' => 'SKU-001',
                                                'quantity' => 1.0,
                                                'item_rate' => 500.00,
                                                'discount' => 50.00,
                                                'taxable_value' => 450.00,
                                                'cgst' => 0.00,
                                                'sgst' => 0.00,
                                                'igst' => 13.50,
                                                'total' => 463.50,
                                                'is_extra_charge' => false,
                                                'confidence' => 98.0,
                                                'page_number' => 1,
                                            ],
                                        ],
                                        'totals' => [
                                            'total_taxable_value' => 450.00,
                                            'total_cgst' => 0.00,
                                            'total_sgst' => 0.00,
                                            'total_igst' => 13.50,
                                            'total_amount' => 463.50,
                                        ],
                                        'confidence_scores' => [
                                            'invoice_date' => 99.0,
                                            'invoice_number' => 99.0,
                                            'po_number' => 95.0,
                                            'order_number' => 99.0,
                                            'buyer_name' => 98.0,
                                            'address' => 97.0,
                                            'state' => 98.0,
                                            'overall' => 98.0,
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $gemini = app(GeminiInvoiceService::class);
        $gemini->setApiKey('test-dummy-api-key');
        $gemini->setModel('gemini-1.5-flash');

        $result = $gemini->extractIndianInvoice("Test invoice text content");

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_fallback']);
        $this->assertEquals('GEMINI-INV-9900', $result['data']['invoice']['invoice_number']);
        $this->assertEquals('Rahul Sharma', $result['data']['invoice']['buyer_name']);
        $this->assertEquals(463.50, $result['data']['totals']['total_amount']);
        $this->assertEquals(98.0, $result['confidence']);
    }

    /**
     * Test Gemini API handles rate limits (HTTP 429) and errors by falling back gracefully.
     */
    public function test_gemini_service_handles_rate_limits_and_falls_back_gracefully(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response('Too Many Requests', 429),
        ]);

        $gemini = app(GeminiInvoiceService::class);
        $gemini->setApiKey('test-dummy-api-key');

        $result = $gemini->extractIndianInvoice("Invoice No: FLB-999. Date: 12-11-2026. Buyer: Amit Kumar. Rate: Rs. 500");

        // Graceful fallback to rule-based engine
        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_fallback']);
        $this->assertNotEmpty($result['warnings']);
    }
}
