<?php

namespace Tests\Feature;

use App\Jobs\ShippingLabelJob;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\Invoice;
use App\Models\ShippingLabel;
use App\Models\User;
use App\Repositories\ShippingLabelRepository;
use App\Services\Shipping\ShippingLabelExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShippingLabelExtractionTest extends TestCase
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
     * Test extracting Flipkart E-Kart shipping labels across multiple pages.
     */
    public function test_extracts_flipkart_ekart_shipping_labels_across_multiple_pages(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Flipkart Batch',
            'total_files' => 1,
            'total_pages' => 2,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $document = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'flipkart_labels.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->flipkartPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $service = app(ShippingLabelExtractionService::class);
        $labels = $service->extractDocument($document);

        $this->assertCount(2, $labels);

        // Page 1 Label Assertions
        $p1 = $labels->firstWhere('page_number', 1);
        $this->assertNotNull($p1);
        $this->assertEquals('FMPC6756481216', $p1->awb_number); // 1. Shipping ID / AWB Number
        $this->assertEquals('OD439142473682225100', $p1->order_number); // 2. Order Number
        $this->assertEquals('Sangeeta Soni', $p1->buyer_name); // 3. Buyer Name
        $this->assertStringContainsString('Maharajganj', $p1->shipping_address); // 4. Complete Address
        $this->assertEquals('Uttar Pradesh', $p1->state); // 5. State
        $this->assertEquals('221314', $p1->pincode); // 6. Pincode
        $this->assertEquals('E-Kart Logistics', $p1->courier_name); // 7. Courier Name
        $this->assertEquals('Flipkart', $p1->marketplace_name); // 8. Marketplace Name
        $this->assertEquals(ShippingLabel::FORMAT_FLIPKART_EKART, $p1->label_format);

        // Page 2 Label Assertions
        $p2 = $labels->firstWhere('page_number', 2);
        $this->assertNotNull($p2);
        $this->assertEquals('FMPC6750490654', $p2->awb_number);
        $this->assertEquals('OD439126462058700100', $p2->order_number);
        $this->assertEquals('Burhan Uddin', $p2->buyer_name);
        $this->assertStringContainsString('Bhimarali', $p2->shipping_address);
        $this->assertEquals('Assam', $p2->state);
        $this->assertEquals('782435', $p2->pincode);
        $this->assertEquals('E-Kart Logistics', $p2->courier_name);
        $this->assertEquals('Flipkart', $p2->marketplace_name);

        // Database verification
        $this->assertDatabaseHas('shipping_labels', [
            'document_id' => $document->id,
            'awb_number' => 'FMPC6756481216',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'pincode' => '221314',
        ]);
    }

    /**
     * Test extracting Delhivery shipping labels with rules:
     * - Do not confuse return codes with AWB.
     * - Do not confuse destination codes with AWB.
     * - Do not use seller address as customer address.
     */
    public function test_extracts_delhivery_shipping_labels_with_strict_rules(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Delhivery Batch',
            'total_files' => 1,
            'total_pages' => 2,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $document = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'delhivery_labels.pdf',
            'stored_path' => 'tests/Fixtures/dreesti_delhivery_shipping_labels.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->dreestiPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $service = app(ShippingLabelExtractionService::class);
        $labels = $service->extractDocument($document);

        $this->assertCount(2, $labels);

        // Page 1 Label Assertions
        $p1 = $labels->firstWhere('page_number', 1);
        $this->assertNotNull($p1);

        // Strict Rule 1: Extract the actual AWB, NOT the return code (141014,4591669)
        $this->assertEquals('1490843814423655', $p1->awb_number);
        $this->assertNotEquals('141014,4591669', $p1->awb_number);
        $this->assertFalse(str_contains($p1->awb_number, ','));

        // Strict Rule 2: Do not confuse destination codes (Chandigarh_Raiprvl) with AWB
        $this->assertFalse(str_contains($p1->awb_number, 'Chandigarh'));
        $this->assertFalse(str_contains($p1->awb_number, 'Raiprvl'));

        // Strict Rule 3: Do not use seller address (DREESTI) as customer address
        $this->assertStringNotContainsString('DREESTI', $p1->buyer_name);
        $this->assertEquals('Deep Singh', $p1->buyer_name);
        $this->assertStringContainsString('262 hallo majra', $p1->shipping_address);
        $this->assertStringNotContainsString('GANPATHI VIHAR', $p1->shipping_address);

        // Strict Rule 4: Extract state and pincode
        $this->assertEquals('Chandigarh', $p1->state);
        $this->assertEquals('160002', $p1->pincode);
        $this->assertEquals('Delhivery', $p1->courier_name);
        $this->assertEquals('351974314617931328', $p1->order_number);

        // Page 2 Label Assertions
        $p2 = $labels->firstWhere('page_number', 2);
        $this->assertNotNull($p2);
        $this->assertEquals('1490844012984690', $p2->awb_number);
        $this->assertEquals('Biswojit Behuria', $p2->buyer_name);
        $this->assertStringContainsString('Bivab Gulmohar', $p2->shipping_address);
        $this->assertEquals('Odisha', $p2->state);
        $this->assertEquals('751012', $p2->pincode);
        $this->assertEquals('Delhivery', $p2->courier_name);
        $this->assertEquals('353944139540232832', $p2->order_number);
    }

    /**
     * Test manual correction of extracted shipping IDs.
     * Preserves original tracking number for full audit trail.
     */
    public function test_allows_manual_correction_of_extracted_shipping_id(): void
    {
        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => '1490843814423655',
            'order_number' => '351974314617931328',
            'buyer_name' => 'Deep Singh',
            'courier_name' => 'Delhivery',
            'label_format' => ShippingLabel::FORMAT_DELHIVERY,
            'confidence_score' => 95.0,
        ]);

        $this->assertFalse($label->is_manually_corrected);
        $this->assertNull($label->original_awb_number);

        // Perform manual correction
        $repo = app(ShippingLabelRepository::class);
        $updated = $repo->updateAwb($label, '1490843814429999', 'Barcode scan correction verified with manifest');

        $this->assertTrue($updated->is_manually_corrected);
        $this->assertEquals('1490843814429999', $updated->awb_number);
        $this->assertEquals('1490843814423655', $updated->original_awb_number);
        $this->assertEquals('Barcode scan correction verified with manifest', $updated->correction_notes);

        // Verify Database has both current and original AWB
        $this->assertDatabaseHas('shipping_labels', [
            'id' => $label->id,
            'awb_number' => '1490843814429999',
            'original_awb_number' => '1490843814423655',
            'is_manually_corrected' => true,
        ]);

        // Verify Activity Log is recorded
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'shipping_label_awb_corrected',
        ]);
    }

    /**
     * Test HTTP web route for manual AWB correction.
     */
    public function test_web_route_for_manual_awb_correction(): void
    {
        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'FMPC6756481216',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'courier_name' => 'E-Kart Logistics',
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('shipping.labels.correctAwb', $label), [
                'awb_number' => 'FMPC6756489999',
                'notes' => 'Corrected via physical barcode inspection',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $label->refresh();
        $this->assertEquals('FMPC6756489999', $label->awb_number);
        $this->assertEquals('FMPC6756481216', $label->original_awb_number);
        $this->assertTrue($label->is_manually_corrected);
    }

    /**
     * Test ShippingLabelJob queue processing.
     */
    public function test_shipping_label_job_dispatches_properly(): void
    {
        Queue::fake();

        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'labels_test.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        ShippingLabelJob::dispatch($document);

        Queue::assertPushed(ShippingLabelJob::class, function ($job) use ($document) {
            return $job->document->id === $document->id;
        });
    }

    /**
     * Test ShippingLabelRepository lookup and matching capabilities.
     */
    public function test_shipping_label_repository_lookups_and_matching(): void
    {
        $repo = app(ShippingLabelRepository::class);

        $label = $repo->create([
            'user_id' => $this->user->id,
            'awb_number' => '1490843814423655',
            'order_number' => '351974314617931328',
            'buyer_name' => 'Deep Singh',
            'courier_name' => 'Delhivery',
            'pincode' => '160002',
            'state' => 'Chandigarh',
        ]);

        $foundByAwb = $repo->findByAwb('1490843814423655', $this->user->id);
        $this->assertNotNull($foundByAwb);
        $this->assertEquals($label->id, $foundByAwb->id);

        $foundByOrder = $repo->findByOrderNumber('351974314617931328', $this->user->id);
        $this->assertNotNull($foundByOrder);
        $this->assertEquals($label->id, $foundByOrder->id);

        // Matching with invoice
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-9901',
            'order_number' => '351974314617931328',
            'total_amount' => 500.00,
        ]);

        $matched = $repo->matchWithInvoice($label, $invoice->id);
        $this->assertTrue($matched);

        $label->refresh();
        $this->assertEquals($invoice->id, $label->invoice_id);
        $this->assertEquals(ShippingLabel::STATUS_MATCHED, $label->status);
    }

    /**
     * Test Gemini AI fallback when layout is unstructured.
     */
    public function test_gemini_fallback_for_unstructured_shipping_labels(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'awb_number' => 'BLUEDART88991122',
                                        'order_number' => 'ORD-554433',
                                        'buyer_name' => 'Vikram Patel',
                                        'shipping_address' => '404 MG Road, Bangalore 560001, Karnataka',
                                        'state' => 'Karnataka',
                                        'pincode' => '560001',
                                        'courier_name' => 'Blue Dart',
                                        'marketplace_name' => 'Amazon India',
                                        'label_format' => 'standard_courier',
                                        'confidence_score' => 97.0,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(ShippingLabelExtractionService::class);
        $aiResult = $service->extractViaGemini("Messy shipping label text with unknown format");

        $this->assertNotNull($aiResult);
        $this->assertEquals('BLUEDART88991122', $aiResult['awb_number']);
        $this->assertEquals('ORD-554433', $aiResult['order_number']);
        $this->assertEquals('Vikram Patel', $aiResult['buyer_name']);
        $this->assertEquals('Karnataka', $aiResult['state']);
        $this->assertEquals('560001', $aiResult['pincode']);
        $this->assertEquals('Blue Dart', $aiResult['courier_name']);
    }
}
