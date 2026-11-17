<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceCorrection;
use App\Models\InvoiceExtractionResult;
use App\Models\InvoiceItem;
use App\Models\InvoiceShippingMatch;
use App\Models\ShippingLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReviewAndEditingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->otherUser = User::factory()->create([
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * 1. INVOICE LIST displays all 9 required columns.
     */
    public function test_invoice_list_displays_all_required_columns(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-TEST-2026-001',
            'invoice_date' => '2026-11-15',
            'po_number' => 'PO-888999',
            'order_number' => 'OD-1122334455',
            'shipping_id' => 'AWB-EKART-9988',
            'buyer_name' => 'Rajesh Sharma',
            'buyer_state' => 'Maharashtra',
            'place_of_supply' => 'Maharashtra (27)',
            'currency' => 'INR',
            'subtotal' => 2500.00,
            'cgst_amount' => 225.00,
            'sgst_amount' => 225.00,
            'total_amount' => 2950.00,
            'processing_status' => 'extracted',
            'matching_status' => 'matched',
            'ai_confidence_score' => 96.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('invoices.index'));

        $response->assertOk();
        // Verify columns headers
        $response->assertSee('Invoice Date');
        $response->assertSee('Invoice Number');
        $response->assertSee('PO Number');
        $response->assertSee('Order Number');
        $response->assertSee('Shipping ID (AWB)');
        $response->assertSee('Buyer Name');
        $response->assertSee('State');
        $response->assertSee('Total');
        $response->assertSee('Processing Status');

        // Verify row values
        $response->assertSee('15 Nov 2026');
        $response->assertSee('INV-TEST-2026-001');
        $response->assertSee('PO-888999');
        $response->assertSee('OD-1122334455');
        $response->assertSee('AWB-EKART-9988');
        $response->assertSee('Rajesh Sharma');
        $response->assertSee('Maharashtra');
        $response->assertSee('2,950.00');
    }

    /**
     * 2. INVOICE DETAILS PAGE renders all 8 required sections.
     */
    public function test_invoice_details_page_renders_all_required_sections(): void
    {
        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => 'invoice',
            'original_filename' => 'flipkart_tax_invoice.pdf',
            'stored_path' => 'documents/flipkart_tax_invoice.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 120400,
            'file_hash' => hash('sha256', 'mock_invoice_content'),
            'total_pages' => 2,
            'extraction_status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'document_id' => $document->id,
            'invoice_number' => 'FAB2600123',
            'invoice_date' => '2026-11-10',
            'po_number' => 'PO-351974314',
            'order_number' => 'OD43914247368',
            'shipping_id' => 'FMPC6756481216',
            'buyer_name' => 'Pooja Hegde',
            'buyer_address' => 'Flat 402, Green Valley Apartments, Bengaluru',
            'buyer_state' => 'Karnataka',
            'place_of_supply' => 'Karnataka (29)',
            'currency' => 'INR',
            'subtotal' => 1000.00,
            'cgst_amount' => 90.00,
            'sgst_amount' => 90.00,
            'igst_amount' => 0.00,
            'tax_amount' => 180.00,
            'total_amount' => 1180.00,
            'processing_status' => 'validated',
            'matching_status' => 'matched',
            'ai_confidence_score' => 94.50,
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_code' => 'SKU-001',
            'description' => 'Wireless Bluetooth Earbuds',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'item_rate' => 1000.00,
            'discount' => 0.00,
            'taxable_value' => 1000.00,
            'cgst' => 90.00,
            'sgst' => 90.00,
            'igst' => 0.00,
            'total_price' => 1180.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        // Section 1: Invoice Information
        $response->assertSee('1. Invoice Information');
        $response->assertSee('FAB2600123');
        $response->assertSee('OD43914247368');

        // Section 2: Buyer Information
        $response->assertSee('2. Buyer Information');
        $response->assertSee('Pooja Hegde');
        $response->assertSee('Green Valley Apartments');

        // Section 3: Shipping Information & Section 8: Matching Status
        $response->assertSee('3. Shipping Information & 8. Matching Status', false);
        $response->assertSee('FMPC6756481216');

        // Section 4: Product details
        $response->assertSee('4. Product Details');
        $response->assertSee('Wireless Bluetooth Earbuds');

        // Section 5: Tax breakdown
        $response->assertSee('5. Tax Breakdown & Integrity', false);
        $response->assertSee('Total CGST');
        $response->assertSee('Total SGST');

        // Section 6: Original PDF preview
        $response->assertSee('6. Original PDF Preview');
        $response->assertSee('flipkart_tax_invoice.pdf');

        // Section 7: AI confidence scores
        $response->assertSee('7. AI Extraction Confidence');
        $response->assertSee('95%'); // 94.5 rounded
    }

    /**
     * 3. EDITING: User can edit invoice fields, buyer information, and shipping ID.
     */
    public function test_user_can_edit_invoice_fields_buyer_address_and_shipping_id(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-ORIG-100',
            'invoice_date' => '2026-11-01',
            'po_number' => 'PO-OLD-01',
            'order_number' => 'ORD-OLD-01',
            'shipping_id' => 'OLD-AWB-1234',
            'buyer_name' => 'Old Customer Name',
            'buyer_address' => 'Old Address 123',
            'buyer_state' => 'Delhi',
            'currency' => 'INR',
            'subtotal' => 500.00,
            'tax_amount' => 50.00,
            'total_amount' => 550.00,
            'processing_status' => 'review_needed',
        ]);

        $updatePayload = [
            'invoice_number' => 'INV-NEW-200',
            'invoice_date' => '2026-11-12',
            'po_number' => 'PO-NEW-99',
            'order_number' => 'ORD-NEW-99',
            'shipping_id' => 'NEW-AWB-5678',
            'buyer_name' => 'Updated Customer Name',
            'buyer_address' => 'New Street 456, Gurugram',
            'buyer_state' => 'Haryana',
            'place_of_supply' => 'Haryana (06)',
            'currency' => 'INR',
            'taxable_value' => 600.00,
            'cgst_amount' => 30.00,
            'sgst_amount' => 30.00,
            'igst_amount' => 0.00,
            'tax_amount' => 60.00,
            'discount_amount' => 0.00,
            'shipping_amount' => 0.00,
            'total_amount' => 660.00,
            'payment_status' => 'paid',
            'processing_status' => 'validated',
            'items' => [
                [
                    'description' => 'Updated Product Item',
                    'quantity' => 2,
                    'item_rate' => 300.00,
                    'discount' => 0.00,
                    'taxable_value' => 600.00,
                    'cgst' => 30.00,
                    'sgst' => 30.00,
                    'igst' => 0.00,
                    'total_price' => 660.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('invoices.update', $invoice), $updatePayload);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('success');

        $invoice->refresh();

        $this->assertEquals('INV-NEW-200', $invoice->invoice_number);
        $this->assertEquals('2026-11-12', $invoice->invoice_date->format('Y-m-d'));
        $this->assertEquals('PO-NEW-99', $invoice->po_number);
        $this->assertEquals('ORD-NEW-99', $invoice->order_number);
        $this->assertEquals('NEW-AWB-5678', $invoice->shipping_id);
        $this->assertEquals('Updated Customer Name', $invoice->buyer_name);
        $this->assertEquals('Haryana', $invoice->buyer_state);
        $this->assertEquals('New Street 456, Gurugram', $invoice->buyer_address);
        $this->assertEquals(660.00, (float) $invoice->total_amount);
        $this->assertEquals('validated', $invoice->processing_status);
        $this->assertTrue($invoice->is_manually_edited);

        // Verify audit log of corrections
        $this->assertDatabaseHas('invoice_corrections', [
            'invoice_id' => $invoice->id,
            'field_name' => 'invoice_number',
            'original_value' => 'INV-ORIG-100',
            'updated_value' => 'INV-NEW-200',
            'updated_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('invoice_corrections', [
            'invoice_id' => $invoice->id,
            'field_name' => 'shipping_id',
            'original_value' => 'OLD-AWB-1234',
            'updated_value' => 'NEW-AWB-5678',
            'updated_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('invoice_corrections', [
            'invoice_id' => $invoice->id,
            'field_name' => 'buyer_name',
            'original_value' => 'Old Customer Name',
            'updated_value' => 'Updated Customer Name',
            'updated_by' => $this->user->id,
        ]);
    }

    /**
     * 4. EDITING: Add and delete product rows with real-time recalculation persistence.
     */
    public function test_user_can_add_edit_and_delete_product_rows(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-ITEMS-001',
            'currency' => 'INR',
            'subtotal' => 200.00,
            'total_amount' => 200.00,
            'processing_status' => 'extracted',
        ]);

        $item1 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Original Item 1 (To be deleted)',
            'quantity' => 1,
            'item_rate' => 100.00,
            'total_price' => 100.00,
        ]);

        $item2 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Original Item 2 (To be modified)',
            'quantity' => 1,
            'item_rate' => 100.00,
            'total_price' => 100.00,
        ]);

        // Submit with Item 1 deleted, Item 2 modified, and Item 3 newly added
        $payload = [
            'invoice_number' => 'INV-ITEMS-001',
            'currency' => 'INR',
            'taxable_value' => 350.00,
            'total_amount' => 350.00,
            'items' => [
                // Item 2 modified
                [
                    'id' => $item2->id,
                    'description' => 'Original Item 2 (Modified)',
                    'quantity' => 1,
                    'item_rate' => 150.00,
                    'taxable_value' => 150.00,
                    'total_price' => 150.00,
                ],
                // Item 3 added
                [
                    'description' => 'Brand New Row Item 3',
                    'quantity' => 2,
                    'item_rate' => 100.00,
                    'taxable_value' => 200.00,
                    'total_price' => 200.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('invoices.update', $invoice), $payload);

        $response->assertRedirect(route('invoices.show', $invoice));

        // Assert item 1 was removed
        $this->assertDatabaseMissing('invoice_items', [
            'description' => 'Original Item 1 (To be deleted)',
        ]);

        // Assert modified and new items exist
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Original Item 2 (Modified)',
            'item_rate' => 150.00,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Brand New Row Item 3',
            'total_price' => 200.00,
        ]);

        // Assert addition was tracked in audit log
        $this->assertDatabaseHas('invoice_corrections', [
            'invoice_id' => $invoice->id,
            'field_name' => 'item_added',
            'updated_by' => $this->user->id,
        ]);
    }

    /**
     * 5. VALIDATION: Highlights missing fields, low confidence, unmatched shipping, duplicate invoice, and math discrepancies.
     */
    public function test_validation_highlights_discrepancies_and_issues(): void
    {
        // Create duplicate invoice pre-existing
        Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'DUP-12345',
            'total_amount' => 500.00,
            'processing_status' => 'validated',
        ]);

        // Create problematic invoice:
        // - Missing invoice_date, buyer_name, buyer_state
        // - Duplicate invoice_number
        // - Low AI confidence (60%)
        // - Unmatched shipping
        // - Tax math discrepancy (total_amount 1000 != items sum 500)
        $problemInvoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'DUP-12345',
            'invoice_date' => null, // Missing
            'order_number' => null, // Missing
            'po_number' => null,    // Missing
            'buyer_name' => null,   // Missing
            'buyer_state' => null,  // Missing
            'shipping_id' => null,  // Unmatched
            'currency' => 'INR',
            'subtotal' => 500.00,
            'tax_amount' => 0.00,
            'total_amount' => 1000.00, // Discrepancy!
            'processing_status' => 'review_needed',
            'matching_status' => 'unmatched',
            'ai_confidence_score' => 60.00, // Low confidence (< 85%)
        ]);

        InvoiceItem::create([
            'invoice_id' => $problemInvoice->id,
            'description' => 'Sole Item',
            'quantity' => 1,
            'item_rate' => 500.00,
            'total_price' => 500.00, // Sum = 500, but invoice total is 1000!
        ]);

        $issues = $problemInvoice->validation_issues;

        $this->assertTrue($issues['has_issues']);
        $this->assertContains('Invoice Date', $issues['missing_fields']);
        $this->assertContains('Buyer Name', $issues['missing_fields']);
        $this->assertContains('Order / PO Number', $issues['missing_fields']);
        $this->assertTrue($issues['is_low_confidence']);
        $this->assertTrue($issues['is_unmatched_shipping']);
        $this->assertTrue($issues['is_possible_duplicate']);
        $this->assertTrue($issues['has_tax_discrepancy']);

        // Inspect UI rendering of highlights
        $response = $this->actingAs($this->user)->get(route('invoices.show', $problemInvoice));
        $response->assertOk();
        $response->assertSee('Data Review & Validation Notice', false);
        $response->assertSee('Missing Required Fields');
        $response->assertSee('Low AI Confidence');
        $response->assertSee('Unmatched Shipping Label');
        $response->assertSee('Possible Duplicate Invoice');
        $response->assertSee('Tax Calculation Discrepancy');
    }

    /**
     * 6. DATABASE REQUIREMENTS: Original AI extracted data is preserved separately.
     */
    public function test_original_extracted_values_remain_preserved_separately(): void
    {
        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => 'invoice',
            'original_filename' => 'original_sample.pdf',
            'stored_path' => 'documents/original_sample.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 50000,
            'file_hash' => hash('sha256', 'sample_content_original'),
            'total_pages' => 1,
            'extraction_status' => 'completed',
        ]);

        $originalData = [
            'invoice' => [
                'invoice_number' => 'ORIG-AI-999',
                'invoice_date' => '2026-11-05',
                'po_number' => 'PO-ORIG',
                'order_number' => 'OD-ORIG',
                'buyer_name' => 'Original Extracted Customer',
                'buyer_state' => 'Karnataka',
                'total_amount' => 1500.00,
            ],
            'products' => [
                [
                    'item_name' => 'Original AI Item',
                    'item_rate' => 1500.00,
                    'taxable_value' => 1500.00,
                    'cgst' => 0.00,
                    'sgst' => 0.00,
                    'igst' => 0.00,
                    'total' => 1500.00,
                ],
            ],
        ];

        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'document_id' => $document->id,
            'invoice_number' => 'ORIG-AI-999',
            'invoice_date' => '2026-11-05',
            'po_number' => 'PO-ORIG',
            'order_number' => 'OD-ORIG',
            'buyer_name' => 'Original Extracted Customer',
            'buyer_state' => 'Karnataka',
            'total_amount' => 1500.00,
            'processing_status' => 'extracted',
        ]);

        InvoiceExtractionResult::create([
            'document_id' => $document->id,
            'invoice_id' => $invoice->id,
            'user_id' => $this->user->id,
            'invoice_number' => 'ORIG-AI-999',
            'extracted_data' => $originalData,
            'overall_confidence' => 95.0,
            'status' => 'completed',
        ]);

        // User edits the invoice with completely new values
        $updatePayload = [
            'invoice_number' => 'EDITED-USER-111',
            'invoice_date' => '2026-11-20',
            'currency' => 'INR',
            'buyer_name' => 'Manually Edited Customer',
            'buyer_state' => 'Kerala',
            'taxable_value' => 2000.00,
            'total_amount' => 2000.00,
            'items' => [
                [
                    'description' => 'User Edited Item',
                    'item_rate' => 2000.00,
                    'taxable_value' => 2000.00,
                    'total_price' => 2000.00,
                ],
            ],
        ];

        $this->actingAs($this->user)->put(route('invoices.update', $invoice), $updatePayload);

        $invoice->refresh();

        // Active invoice fields are updated
        $this->assertEquals('EDITED-USER-111', $invoice->invoice_number);
        $this->assertEquals('Manually Edited Customer', $invoice->buyer_name);

        // Original extraction table still has the untouched AI extraction
        $extractionRecord = InvoiceExtractionResult::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($extractionRecord);
        $this->assertEquals('ORIG-AI-999', $extractionRecord->extracted_data['invoice']['invoice_number']);
        $this->assertEquals('Original Extracted Customer', $extractionRecord->extracted_data['invoice']['buyer_name']);

        // View model provides access to original extracted data
        $this->assertEquals('ORIG-AI-999', $invoice->original_extracted_data['invoice']['invoice_number']);
    }

    /**
     * 7. TRANSACTION ATOMICITY: Database transactions rollback on error.
     */
    public function test_database_transaction_ensures_atomicity_on_error(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-SAFE-001',
            'currency' => 'INR',
            'total_amount' => 500.00,
            'processing_status' => 'extracted',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Safe Item',
            'quantity' => 1,
            'item_rate' => 500.00,
            'total_price' => 500.00,
        ]);

        // Attempt invalid payload with missing required invoice_number
        $invalidPayload = [
            'invoice_number' => '', // Will fail validation
            'currency' => 'INR',
            'total_amount' => 800.00,
        ];

        $response = $this->actingAs($this->user)->put(route('invoices.update', $invoice), $invalidPayload);
        $response->assertSessionHasErrors(['invoice_number']);

        $invoice->refresh();
        $this->assertEquals('INV-SAFE-001', $invoice->invoice_number);
        $this->assertEquals(500.00, (float) $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
    }
}
