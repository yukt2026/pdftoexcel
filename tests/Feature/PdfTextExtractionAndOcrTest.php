<?php

namespace Tests\Feature;

use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\DocumentPage;
use App\Models\User;
use App\Services\Document\DocumentProcessingService;
use App\Services\OCR\InvoiceOcrService;
use App\Services\Pdf\PdfPageRendererService;
use App\Services\Pdf\PdfTextExtractionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfTextExtractionAndOcrTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected PdfTextExtractionService $extractor;
    protected DocumentProcessingService $documentProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('role', User::ROLE_USER)->first();
        $this->extractor = app(PdfTextExtractionService::class);
        $this->documentProcessor = app(DocumentProcessingService::class);

        Storage::fake('local');
    }

    /**
     * Helper to create a fake PDF with specified raw content.
     */
    protected function createPdfFile(string $content, string $filename = 'test.pdf'): string
    {
        $path = "documents/test/{$filename}";
        Storage::disk('local')->put($path, $content);
        return Storage::disk('local')->path($path);
    }

    public function test_flipkart_invoice_and_ekart_shipping_label_sample_is_extracted_correctly(): void
    {
        $samplePath = base_path('tests/Fixtures/flipkart_invoice_and_ekart_label.pdf');
        $this->assertFileExists($samplePath, 'Flipkart sample PDF must exist in tests/Fixtures.');

        $result = $this->extractor->extractText($samplePath);

        // 1. Structural verification
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['page_count']);
        $this->assertCount(2, $result['pages']);
        $this->assertFalse($result['is_scanned'], 'Flipkart PDF is digital text with high character density.');
        $this->assertFalse($result['has_password']);
        $this->assertFalse($result['is_corrupted']);

        // 2. Page 1 Content Verification (Flipkart Invoice + E-Kart Label 1)
        $page1 = $result['pages'][0];
        $this->assertEquals(1, $page1['page_number']);
        $this->assertGreaterThan(1000, $page1['char_count']);
        $this->assertStringContainsString('E-Kart Logistics', $page1['text']);
        $this->assertStringContainsString('OD439142473682225100', $page1['text']);
        $this->assertStringContainsString('GATIMAAN ENTERPRISES', $page1['text']);
        $this->assertStringContainsString('03EWIPM1640G1ZS', $page1['text']); // GSTIN
        $this->assertStringContainsString('Tax Invoice', $page1['text']);
        $this->assertStringContainsString('NUBFYP0270000004', $page1['text']); // Invoice No
        $this->assertStringContainsString('FMPC6756481216', $page1['text']); // Tracking ID

        // 3. Page 2 Content Verification (Flipkart Invoice + E-Kart Label 2)
        $page2 = $result['pages'][1];
        $this->assertEquals(2, $page2['page_number']);
        $this->assertGreaterThan(1000, $page2['char_count']);
        $this->assertStringContainsString('E-Kart Logistics', $page2['text']);
        $this->assertStringContainsString('OD439126462058700100', $page2['text']);
        $this->assertStringContainsString('NUBEQ3O270000005', $page2['text']); // Invoice No 2
        $this->assertStringContainsString('FMPC6750490654', $page2['text']);

        // 4. Indian marker heuristics
        $this->assertContains('03EWIPM1640G1ZS', $result['metadata']['detected_markers']['gstins']);
        $this->assertContains('OD439142473682225100', $result['metadata']['detected_markers']['order_ids']);
        $this->assertContains('E-Kart Logistics', $result['metadata']['detected_markers']['detected_carriers']);
    }

    public function test_dreesti_and_delhivery_shipping_label_sample_is_extracted_correctly(): void
    {
        $samplePath = base_path('tests/Fixtures/dreesti_delhivery_shipping_labels.pdf');
        $this->assertFileExists($samplePath, 'DREESTI & Delhivery sample PDF must exist in tests/Fixtures.');

        $result = $this->extractor->extractText($samplePath);

        // 1. Structural verification
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['page_count']);
        $this->assertCount(2, $result['pages']);
        $this->assertFalse($result['is_scanned']);
        $this->assertFalse($result['has_password']);
        $this->assertFalse($result['is_corrupted']);

        // 2. Page 1 Content Verification (DREESTI + Delhivery Label 1)
        $page1 = $result['pages'][0];
        $this->assertEquals(1, $page1['page_number']);
        $this->assertGreaterThan(800, $page1['char_count']);
        $this->assertStringContainsString('DREESTI', $page1['text']);
        $this->assertStringContainsString('Delhivery', $page1['text']);
        $this->assertStringContainsString('Chandigarh', $page1['text']);
        $this->assertStringContainsString('141014', $page1['text']); // Pincode
        $this->assertStringContainsString('gpzdk27301', $page1['text']); // Invoice No
        $this->assertStringContainsString('03ASJPM4611Q1ZH', $page1['text']); // GSTIN

        // 3. Page 2 Content Verification (DREESTI + Delhivery Label 2)
        $page2 = $result['pages'][1];
        $this->assertEquals(2, $page2['page_number']);
        $this->assertGreaterThan(800, $page2['char_count']);
        $this->assertStringContainsString('DREESTI', $page2['text']);
        $this->assertStringContainsString('Delhivery', $page2['text']);
        $this->assertStringContainsString('Bhubaneswar', $page2['text']);
        $this->assertStringContainsString('gpzdk27304', $page2['text']); // Invoice No 2
        $this->assertStringContainsString('353944139540232832', $page2['text']); // Order No 2

        // 4. Logistics & Marker detection
        $this->assertContains('Delhivery', $result['metadata']['detected_markers']['detected_carriers']);
        $this->assertContains('03ASJPM4611Q1ZH', $result['metadata']['detected_markers']['gstins']);
    }

    public function test_document_processing_service_persists_raw_text_and_page_records(): void
    {
        $samplePath = base_path('tests/Fixtures/flipkart_invoice_and_ekart_label.pdf');
        $storedRelPath = 'documents/invoices/2026/11/sample_flipkart.pdf';

        Storage::disk('local')->put($storedRelPath, file_get_contents($samplePath));

        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Sample Ingestion Batch',
            'status' => 'pending',
        ]);

        $document = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'flipkart_invoice_sample.pdf',
            'stored_path' => $storedRelPath,
            'mime_type' => 'application/pdf',
            'file_size' => filesize($samplePath),
            'file_hash' => hash_file('sha256', $samplePath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        // Run processor
        $processedDoc = $this->documentProcessor->process($document);

        $this->assertEquals(Document::STATUS_COMPLETED, $processedDoc->extraction_status);
        $this->assertNotNull($processedDoc->raw_text);
        $this->assertStringContainsString('E-Kart Logistics', $processedDoc->raw_text);
        $this->assertStringContainsString('NUBFYP0270000004', $processedDoc->raw_text);

        // Check document_pages records
        $pages = DocumentPage::where('document_id', $document->id)->orderBy('page_number')->get();
        $this->assertCount(2, $pages);
        $this->assertEquals(1, $pages[0]->page_number);
        $this->assertEquals(2, $pages[1]->page_number);
        $this->assertStringContainsString('OD439142473682225100', $pages[0]->extracted_text);
        $this->assertStringContainsString('OD439126462058700100', $pages[1]->extracted_text);

        // Batch status must be recalculated
        $batch->refresh();
        $this->assertEquals(DocumentBatch::STATUS_COMPLETED, $batch->status);
    }

    public function test_scanned_pdf_page_is_detected_and_handled(): void
    {
        // Standard valid 1-page PDF structure with 0 selectable text (empty page)
        $scannedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "xref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n"
            . "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n165\n%%EOF";

        $storedPath = 'documents/scanned/scanned_sample.pdf';
        Storage::disk('local')->put($storedPath, $scannedPdf);
        $fullPath = Storage::disk('local')->path($storedPath);

        // Verify detection on text extraction level
        $extraction = $this->extractor->extractText($fullPath);
        $this->assertTrue($extraction['is_scanned']);
        $this->assertNotEmpty($extraction['pages']);
        $this->assertTrue($extraction['pages'][0]['is_scanned']);

        // Verify OCR Service provider invocation
        $fakeImgPath = Storage::disk('local')->path('documents/test/scan_mock.png');
        Storage::disk('local')->put('documents/test/scan_mock.png', 'fake image bytes');

        $ocrService = app(InvoiceOcrService::class);
        $ocrResult = $ocrService->setProvider('mock')->extractText($fakeImgPath);

        $this->assertTrue($ocrResult['success']);
        $this->assertEquals('mock', $ocrResult['provider']);
        $this->assertStringContainsString('OCR Mock', $ocrResult['text']);
    }

    public function test_password_protected_pdf_is_detected_and_reported(): void
    {
        // Construct a PDF marked with /Encrypt dictionary
        $encryptedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Filter /Standard /V 2 /R 3 /O (hash) /U (hash) /P -4 >>\nendobj\n"
            . "trailer\n<< /Size 5 /Root 1 0 R /Encrypt 4 0 R >>\nstartxref\n250\n%%EOF";

        $storedPath = 'documents/locked/locked_invoice.pdf';
        Storage::disk('local')->put($storedPath, $encryptedPdf);

        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'locked_invoice.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($encryptedPdf),
            'file_hash' => hash('sha256', $encryptedPdf),
            'total_pages' => 1,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $processed = $this->documentProcessor->process($document);

        $this->assertEquals(Document::STATUS_FAILED, $processed->extraction_status);
        $this->assertTrue($processed->has_password);
        $this->assertStringContainsString('password', strtolower($processed->error_message));
    }

    public function test_corrupted_pdf_is_handled_gracefully(): void
    {
        $corruptedBytes = "THIS IS NOT A VALID PDF FILE AT ALL. CORRUPTED BYTES 123456";
        $storedPath = 'documents/corrupt/corrupt.pdf';
        Storage::disk('local')->put($storedPath, $corruptedBytes);

        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'corrupted.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($corruptedBytes),
            'file_hash' => hash('sha256', $corruptedBytes),
            'total_pages' => 1,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $processed = $this->documentProcessor->process($document);

        $this->assertEquals(Document::STATUS_FAILED, $processed->extraction_status);
        $this->assertStringContainsString('corrupt', strtolower($processed->error_message));
    }

    public function test_background_job_extracts_document_text_asynchronously(): void
    {
        $samplePath = base_path('tests/Fixtures/dreesti_delhivery_shipping_labels.pdf');
        $storedRelPath = 'documents/shipping/2026/11/delhivery_sample.pdf';

        Storage::disk('local')->put($storedRelPath, file_get_contents($samplePath));

        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'delhivery_label_sample.pdf',
            'stored_path' => $storedRelPath,
            'mime_type' => 'application/pdf',
            'file_size' => filesize($samplePath),
            'file_hash' => hash_file('sha256', $samplePath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        // Dispatch and execute queue job synchronously in test
        $job = new ExtractDocumentTextJob($document);
        $job->handle($this->documentProcessor);

        $document->refresh();
        $this->assertEquals(Document::STATUS_COMPLETED, $document->extraction_status);
        $this->assertStringContainsString('DREESTI', $document->raw_text);
        $this->assertStringContainsString('Delhivery', $document->raw_text);
        $this->assertEquals(2, $document->pages()->count());
    }

    public function test_authenticated_user_can_view_document_raw_text_page(): void
    {
        $samplePath = base_path('tests/Fixtures/flipkart_invoice_and_ekart_label.pdf');
        $storedRelPath = 'documents/invoices/raw_text_test.pdf';

        Storage::disk('local')->put($storedRelPath, file_get_contents($samplePath));

        $document = Document::create([
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'flipkart_for_view.pdf',
            'stored_path' => $storedRelPath,
            'mime_type' => 'application/pdf',
            'file_size' => filesize($samplePath),
            'file_hash' => hash_file('sha256', $samplePath),
            'total_pages' => 2,
            'extraction_status' => Document::STATUS_PENDING,
        ]);

        $this->documentProcessor->process($document);

        $response = $this->actingAs($this->user)->get(route('documents.rawText', $document));
        $response->assertOk();
        $response->assertSee('flipkart_for_view.pdf');
        $response->assertSee('NUBFYP0270000004');
        $response->assertSee('Page 1');
        $response->assertSee('Page 2');
    }
}
