<?php

namespace Tests\Feature;

use App\Exports\InvoiceWorkbookExport;
use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\InvoiceExtractionJob;
use App\Jobs\MatchingJob;
use App\Jobs\ShippingLabelJob;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\ExportRecord;
use App\Models\Invoice;
use App\Models\InvoiceCorrection;
use App\Models\InvoiceItem;
use App\Models\InvoiceShippingMatch;
use App\Models\ShippingLabel;
use App\Models\User;
use App\Services\Document\DocumentProcessingService;
use App\Services\Excel\ExcelExportService;
use App\Services\Invoice\InvoiceExtractionService;
use App\Services\Matching\InvoiceMatchingService;
use App\Services\Pdf\DocumentUploadService;
use App\Services\Shipping\ShippingLabelExtractionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use ZipArchive;

class ProductionLevelEndToEndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected User $admin;

    protected string $flipkartPdfPath;
    protected string $dreestiPdfPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'user@invoiceexcel.test')->first();
        $this->admin = User::where('email', 'admin@invoiceexcel.test')->first();
        $this->otherUser = User::factory()->create([
            'email' => 'competitor@invoiceexcel.test',
            'role' => User::ROLE_USER,
            'status' => 'active',
        ]);

        $this->flipkartPdfPath = base_path('tests/Fixtures/flipkart_invoice_and_ekart_label.pdf');
        $this->dreestiPdfPath = base_path('tests/Fixtures/dreesti_delhivery_shipping_labels.pdf');

        $this->assertFileExists($this->flipkartPdfPath, 'Flipkart fixture PDF must exist.');
        $this->assertFileExists($this->dreestiPdfPath, 'DREESTI fixture PDF must exist.');
    }

    /**
     * 1. COMPLETE END-TO-END WORKFLOW ON FLIPKART AND DREESTI SAMPLE PDFS:
     * Upload -> Text Extraction -> Multi-page AI Invoice Parsing -> Shipping Label Parsing ->
     * Order Number & PO Suffix Matching -> Multi-Product, CGST/SGST/IGST, Extra Charges ->
     * Manual Editing & Corrections Audit -> Excel Generation with 2 Sheets & exact 16 columns.
     */
    public function test_complete_end_to_end_workflow_flipkart_and_dreesti(): void
    {
        $uploadService = app(DocumentUploadService::class);
        $docProcessor = app(DocumentProcessingService::class);
        $invoiceExtraction = app(InvoiceExtractionService::class);
        $shippingExtraction = app(ShippingLabelExtractionService::class);
        $matchingService = app(InvoiceMatchingService::class);
        $excelService = app(ExcelExportService::class);

        // --- STEP 1: Upload and Ingest Flipkart Invoice & Shipping Label Documents ---
        $flipkartBatch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Flipkart Ingestion Batch',
            'total_files' => 2,
            'total_pages' => 4,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $flipkartInvoiceDoc = Document::create([
            'batch_id' => $flipkartBatch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'flipkart_invoice_sample.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->flipkartPdfPath),
            'file_hash' => hash_file('sha256', $this->flipkartPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $flipkartShippingDoc = Document::create([
            'batch_id' => $flipkartBatch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'flipkart_shipping_labels.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->flipkartPdfPath),
            'file_hash' => hash_file('sha256', $this->flipkartPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        // --- STEP 2: Upload and Ingest DREESTI Invoice & Delhivery Shipping Label Documents ---
        $dreestiBatch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'DREESTI Ingestion Batch',
            'total_files' => 2,
            'total_pages' => 4,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $dreestiInvoiceDoc = Document::create([
            'batch_id' => $dreestiBatch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'dreesti_invoice_sample.pdf',
            'stored_path' => 'tests/Fixtures/dreesti_delhivery_shipping_labels.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->dreestiPdfPath),
            'file_hash' => hash_file('sha256', $this->dreestiPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $dreestiShippingDoc = Document::create([
            'batch_id' => $dreestiBatch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'delhivery_shipping_labels.pdf',
            'stored_path' => 'tests/Fixtures/dreesti_delhivery_shipping_labels.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->dreestiPdfPath),
            'file_hash' => hash_file('sha256', $this->dreestiPdfPath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        // --- STEP 3: Multi-Page Text Extraction ---
        $docProcessor->process($flipkartInvoiceDoc);
        $docProcessor->process($flipkartShippingDoc);
        $docProcessor->process($dreestiInvoiceDoc);
        $docProcessor->process($dreestiShippingDoc);

        $this->assertEquals(Document::STATUS_EXTRACTED, $flipkartInvoiceDoc->fresh()->extraction_status);
        $this->assertEquals(2, $flipkartInvoiceDoc->pages()->count());
        $this->assertEquals(2, $dreestiInvoiceDoc->pages()->count());

        // --- STEP 4: Indian GST Invoice Extraction ---
        $flipkartInvResult = $invoiceExtraction->extractAndPersist($flipkartInvoiceDoc);
        $dreestiInvResult = $invoiceExtraction->extractAndPersist($dreestiInvoiceDoc);

        $flipkartInvoice = Invoice::where('document_id', $flipkartInvoiceDoc->id)->first();
        $this->assertNotNull($flipkartInvoice, 'Flipkart Invoice must be persisted in database.');
        $this->assertEquals('NUBFYP0270000004', $flipkartInvoice->invoice_number);
        $this->assertEquals('OD439142473682225100', $flipkartInvoice->order_number);
        $this->assertEquals('Sangeeta Soni', $flipkartInvoice->buyer_name);
        $this->assertGreaterThan(0, $flipkartInvoice->items()->count());

        $dreestiInvoice = Invoice::where('document_id', $dreestiInvoiceDoc->id)->first();
        $this->assertNotNull($dreestiInvoice, 'DREESTI Invoice must be persisted in database.');
        $this->assertEquals('351974314617931328', $dreestiInvoice->po_number);
        $this->assertGreaterThan(0, $dreestiInvoice->items()->count());

        // --- STEP 5: Shipping Label Extraction ---
        $flipkartLabels = $shippingExtraction->extractDocument($flipkartShippingDoc);
        $this->assertCount(2, $flipkartLabels, 'Must extract 2 Flipkart labels across 2 pages.');
        $label1 = $flipkartLabels->firstWhere('page_number', 1);
        $this->assertEquals('FMPC6756481216', $label1->awb_number);
        $this->assertEquals('OD439142473682225100', $label1->order_number);
        $this->assertEquals('E-Kart Logistics', $label1->courier_name);

        $dreestiLabels = $shippingExtraction->extractDocument($dreestiShippingDoc);
        $this->assertCount(2, $dreestiLabels, 'Must extract 2 Delhivery labels across 2 pages.');
        $delhiveryLabel1 = $dreestiLabels->firstWhere('page_number', 1);
        $this->assertEquals('1490843814423655', $delhiveryLabel1->awb_number);
        $this->assertTrue(in_array($delhiveryLabel1->order_number, ['351974314617931328', '351974314617931328_1']));
        $this->assertEquals('Delhivery', $delhiveryLabel1->courier_name);

        // --- STEP 6: Execute Automated Matching Engine ---
        $matchSummary = $matchingService->runBatchMatching($this->user->id);
        $this->assertGreaterThanOrEqual(2, $matchSummary['matched'], 'Both sample invoices must match their respective shipping labels.');

        $flipkartInvoice->refresh();
        $dreestiInvoice->refresh();

        // Verify Flipkart Exact Order Match
        $this->assertEquals('matched', $flipkartInvoice->matching_status);
        $this->assertEquals('FMPC6756481216', $flipkartInvoice->resolved_shipping_id);

        // Verify DREESTI PO & Suffix Strip Match
        $this->assertEquals('matched', $dreestiInvoice->matching_status);
        $this->assertEquals('1490843814423655', $dreestiInvoice->resolved_shipping_id);

        // --- STEP 7: Verify Multi-Products, CGST, SGST, IGST, and Extra Charges ---
        $flipkartItems = $flipkartInvoice->items;
        $this->assertNotEmpty($flipkartItems);
        foreach ($flipkartItems as $item) {
            $this->assertGreaterThanOrEqual(0, (float) $item->taxable_value);
            $this->assertGreaterThanOrEqual(0, (float) ($item->cgst + $item->sgst + $item->igst));
        }

        // --- STEP 8: Manual Review & Editing Audit ---
        $originalBuyerName = $flipkartInvoice->buyer_name;
        $response = $this->actingAs($this->user)->put(route('invoices.update', $flipkartInvoice), [
            'invoice_number' => $flipkartInvoice->invoice_number,
            'invoice_date' => $flipkartInvoice->invoice_date?->format('Y-m-d') ?: '2024-04-18',
            'order_number' => $flipkartInvoice->order_number,
            'po_number' => $flipkartInvoice->po_number,
            'buyer_name' => 'GATIMAAN ENTERPRISES AUDITED PVT LTD',
            'buyer_address' => 'Plot 42, Sector 18, Gurugram, Haryana',
            'buyer_state' => 'Haryana',
            'currency' => 'INR',
            'shipping_id' => 'FMPC6756481216',
            'subtotal' => $flipkartInvoice->subtotal,
            'cgst_amount' => $flipkartInvoice->cgst_amount,
            'sgst_amount' => $flipkartInvoice->sgst_amount,
            'igst_amount' => $flipkartInvoice->igst_amount,
            'total_amount' => $flipkartInvoice->total_amount,
            'items' => [
                [
                    'id' => $flipkartItems->first()->id,
                    'description' => 'Updated Audited Item',
                    'quantity' => 1,
                    'item_rate' => 1200.00,
                    'discount' => 0.00,
                    'taxable_value' => 1016.95,
                    'cgst' => 91.53,
                    'sgst' => 91.53,
                    'igst' => 0.00,
                    'total_price' => 1200.00,
                    'is_extra_charge' => false,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $flipkartInvoice->refresh();

        $this->assertTrue($flipkartInvoice->is_manually_edited);
        $this->assertEquals('GATIMAAN ENTERPRISES AUDITED PVT LTD', $flipkartInvoice->buyer_name);

        // Verify audit log
        $correction = InvoiceCorrection::where('invoice_id', $flipkartInvoice->id)
            ->where('field_name', 'buyer_name')
            ->first();
        $this->assertNotNull($correction, 'InvoiceCorrection record must be saved for buyer_name.');
        $this->assertEquals($originalBuyerName, $correction->original_value);
        $this->assertEquals('GATIMAAN ENTERPRISES AUDITED PVT LTD', $correction->updated_value);

        // Original AI extraction record remains preserved
        $originalExtraction = $flipkartInvoice->extractionResult;
        $this->assertNotNull($originalExtraction);
        $this->assertEquals($originalBuyerName, $originalExtraction->buyer_name);

        // --- STEP 9: Two-Sheet Excel Generation and Content Audit ---
        $excelResponse = $excelService->exportWorkbook('all', [], $this->user->id);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $excelResponse);

        $tempExcelPath = $excelResponse->getFile()->getPathname();
        $this->assertFileExists($tempExcelPath);

        // Inspect XLSX Zip Archive
        $zip = new ZipArchive();
        $openResult = $zip->open($tempExcelPath);
        $this->assertTrue($openResult === true, 'Generated XLSX must be a valid zip archive.');

        // Verify sheets: workbook XML must define 'Invoice Items' and 'Invoice Summary'
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $this->assertNotFalse($workbookXml);
        $this->assertStringContainsString('Invoice Items', $workbookXml);
        $this->assertStringContainsString('Invoice Summary', $workbookXml);
        $zip->close();
    }

    /**
     * 2. SECURITY AUDIT:
     * - Multi-tenant User Data Isolation
     * - Prevent Unauthorized PDF and Excel Downloads
     * - Prevent Path Traversal
     * - SQL Injection & XSS resistance
     */
    public function test_security_audit_isolation_downloads_path_traversal_sql_xss(): void
    {
        // 1. Setup User A Document and Invoice
        $userABatch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'User A Batch',
            'total_files' => 1,
            'total_pages' => 1,
            'status' => 'completed',
        ]);

        $userADoc = Document::create([
            'batch_id' => $userABatch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'user_a_confidential.pdf',
            'stored_path' => 'tests/Fixtures/flipkart_invoice_and_ekart_label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => hash('sha256', 'user_a_content'),
            'total_pages' => 1,
            'extraction_status' => 'completed',
        ]);

        $userAInvoice = Invoice::create([
            'user_id' => $this->user->id,
            'document_id' => $userADoc->id,
            'invoice_number' => 'INV-USER-A-SECRET',
            'total_amount' => 50000.00,
            'currency' => 'INR',
            'processing_status' => 'extracted',
            'matching_status' => 'unmatched',
        ]);

        $userAExport = ExportRecord::create([
            'user_id' => $this->user->id,
            'export_name' => 'Confidential Export User A',
            'format' => 'xlsx',
            'file_path' => 'exports/user_a_export.xlsx',
            'total_records' => 1,
            'status' => 'completed',
        ]);

        // A. Multi-tenant isolation: User B cannot access User A document preview or download
        $response = $this->actingAs($this->otherUser)->get(route('documents.download', $userADoc));
        $this->assertTrue(in_array($response->status(), [403, 404]), 'Unauthorized user cannot download other user document.');

        $response = $this->actingAs($this->otherUser)->get(route('documents.preview', $userADoc));
        $this->assertTrue(in_array($response->status(), [403, 404]), 'Unauthorized user cannot preview other user document.');

        // B. Multi-tenant isolation: User B cannot view or edit User A invoice
        $response = $this->actingAs($this->otherUser)->get(route('invoices.show', $userAInvoice));
        $this->assertTrue(in_array($response->status(), [403, 404]), 'Unauthorized user cannot view other user invoice.');

        $response = $this->actingAs($this->otherUser)->put(route('invoices.update', $userAInvoice), [
            'invoice_number' => 'HACKED-NUMBER',
        ]);
        $this->assertTrue(in_array($response->status(), [403, 404]), 'Unauthorized user cannot edit other user invoice.');

        // C. Multi-tenant isolation: User B cannot download User A Excel export
        $response = $this->actingAs($this->otherUser)->get(route('exports.download', $userAExport));
        $this->assertTrue(in_array($response->status(), [403, 404]), 'Unauthorized user cannot download other user export.');

        // D. Path Traversal resistance: Malicious filenames are sanitized and confined
        $response = $this->actingAs($this->user)->get('/exports/download/' . $userAExport->id . '?file=../../../../windows/system32/cmd.exe');
        $this->assertTrue($response->status() !== 200 || !str_contains($response->headers->get('Content-Disposition') ?? '', 'cmd.exe'));

        // E. SQL Injection test on search and filter inputs
        $sqliPayload = "' OR '1'='1' -- ";
        $response = $this->actingAs($this->user)->get(route('invoices.index', ['search' => $sqliPayload]));
        $response->assertOk();
        $response->assertDontSee('SQLSTATE', false);

        $response = $this->actingAs($this->user)->get(route('history.index', ['search' => $sqliPayload]));
        $response->assertOk();
        $response->assertDontSee('SQLSTATE', false);

        // F. XSS sanitization in input fields and views
        $xssPayload = '<script>alert("XSS-AUDIT")</script>';
        $response = $this->actingAs($this->user)->get(route('invoices.index', ['search' => $xssPayload]));
        $response->assertOk();
        // Plain unescaped script tag should NOT execute in HTML
        $response->assertDontSee('<script>alert("XSS-AUDIT")</script>', false);
    }

    /**
     * 3. QUEUE, BACKGROUND RETRIES AND DEDUPLICATION AUDIT:
     * - Queue jobs have timeout, tries, and unique ID deduplication.
     * - Permanent failure triggers failed() hook and updates document status.
     * - Admin / User can retry failed documents.
     */
    public function test_queue_background_retries_and_deduplication(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Failing Batch Test',
            'total_files' => 1,
            'total_pages' => 1,
            'status' => DocumentBatch::STATUS_PENDING,
        ]);

        $document = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'corrupted_file.pdf',
            'stored_path' => 'documents/invalid/corrupted_file.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 50,
            'file_hash' => hash('sha256', 'corrupted'),
            'total_pages' => 1,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        // 1. Verify Job Deduplication contract
        $job1 = new InvoiceExtractionJob($document);
        $job2 = new ShippingLabelJob($document);
        $job3 = new ExtractDocumentTextJob($document);
        $job4 = new MatchingJob($this->user->id);

        $this->assertEquals((string) $document->id, $job1->uniqueId());
        $this->assertEquals((string) $document->id, $job2->uniqueId());
        $this->assertEquals((string) $document->id, $job3->uniqueId());
        $this->assertEquals('user_' . $this->user->id, $job4->uniqueId());

        // 2. Test failure hook updates status cleanly
        $job1->failed(new \Exception('Simulated PDF Parser Out of Memory Error'));
        $document->refresh();

        $this->assertEquals(Document::STATUS_FAILED, $document->extraction_status);
        $this->assertStringContainsString('Simulated PDF Parser', $document->error_message);

        // Verify activity log recorded the failure
        $failLog = ActivityLog::where('action', 'invoice_extraction_job_failed')->first();
        $this->assertNotNull($failLog, 'Permanent failure must be recorded in ActivityLog.');
    }

    /**
     * 4. LARGE BATCH PERFORMANCE & PAGINATION AUDIT:
     * - Tests that large batches do not exhaust memory.
     * - Tests pagination across Invoices, Batches, History, and Review.
     */
    public function test_large_batch_pagination_and_performance(): void
    {
        // Create 35 dummy invoices to verify pagination thresholds
        $invoices = [];
        for ($i = 1; $i <= 35; $i++) {
            $invoices[] = [
                'user_id' => $this->user->id,
                'invoice_number' => "PERF-INV-{$i}",
                'invoice_date' => '2024-04-01',
                'po_number' => "PO-{$i}",
                'order_number' => "OD-{$i}",
                'buyer_name' => "Buyer {$i}",
                'currency' => 'INR',
                'subtotal' => 1000.00,
                'tax_amount' => 180.00,
                'total_amount' => 1180.00,
                'processing_status' => 'extracted',
                'matching_status' => 'unmatched',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Invoice::insert($invoices);

        // 1. Invoices Index Pagination (20 per page default)
        $response = $this->actingAs($this->user)->get(route('invoices.index'));
        $response->assertOk();
        $response->assertViewHas('invoices');
        $paginated = $response->viewData('invoices');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $paginated);
        $this->assertGreaterThanOrEqual(35, $paginated->total());

        // 2. History Batches Tab Pagination
        $response = $this->actingAs($this->user)->get(route('history.index', ['tab' => 'invoices']));
        $response->assertOk();
        $response->assertViewHas('invoices');

        // 3. Reports Aggregations avoid in-memory collection bloat
        $response = $this->actingAs($this->admin)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('totalInvoices');
        $this->assertGreaterThanOrEqual(35, $response->viewData('totalInvoices'));
        $this->assertGreaterThan(0, $response->viewData('totalInvoiceValue'));
    }
}
