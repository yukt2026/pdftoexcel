<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('role', User::ROLE_USER)->first();
        $this->admin = User::where('role', User::ROLE_ADMIN)->first();

        $this->otherUser = User::create([
            'name' => 'Other Regular User',
            'email' => 'otheruser@example.test',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_USER,
            'status' => 'active',
        ]);

        Storage::fake('local');
    }

    /**
     * Create a realistic test PDF with standard PDF header and trailer.
     */
    protected function createFakePdf(string $filename, string $uniqueContent = ''): UploadedFile
    {
        $pdfContent = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "% Comment: {$uniqueContent}\n"
            . "xref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n"
            . "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n165\n%%EOF";

        return UploadedFile::fake()->createWithContent($filename, $pdfContent);
    }

    public function test_user_can_upload_multiple_invoice_pdfs_in_one_batch(): void
    {
        $pdf1 = $this->createFakePdf('invoice_batch_1.pdf', 'Unique invoice 1');
        $pdf2 = $this->createFakePdf('invoice_batch_2.pdf', 'Unique invoice 2');
        $pdf3 = $this->createFakePdf('invoice_batch_3.pdf', 'Unique invoice 3');

        $response = $this->actingAs($this->user)->post(route('uploads.store'), [
            'batch_name' => 'Q4 Vendor Invoices Batch',
            'process_immediately' => '0',
            'documents' => [$pdf1, $pdf2, $pdf3],
        ]);

        // Expect redirect to batch show page
        $batch = DocumentBatch::where('batch_name', 'Q4 Vendor Invoices Batch')->first();
        $this->assertNotNull($batch);
        $response->assertRedirect(route('batches.show', $batch));

        $this->assertEquals($this->user->id, $batch->user_id);
        $this->assertEquals(3, $batch->total_files);
        $this->assertGreaterThanOrEqual(3, $batch->total_pages);

        // Check documents table
        $this->assertDatabaseHas('documents', [
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'invoice_batch_1.pdf',
        ]);
        $this->assertDatabaseHas('documents', [
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'invoice_batch_2.pdf',
        ]);
        $this->assertDatabaseHas('documents', [
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => Document::TYPE_INVOICE,
            'original_filename' => 'invoice_batch_3.pdf',
        ]);

        // Verify documents are stored securely with UUID and not the original filename
        $doc = Document::where('original_filename', 'invoice_batch_1.pdf')->first();
        $this->assertNotNull($doc->file_hash);
        $this->assertFalse(str_contains($doc->stored_path, 'invoice_batch_1.pdf'));
        $this->assertTrue(Storage::disk('local')->exists($doc->stored_path));
    }

    public function test_user_can_upload_multiple_shipping_label_pdfs_in_one_batch(): void
    {
        $pdf1 = $this->createFakePdf('shipping_waybill_1.pdf', 'Waybill Fedex #1 TRK-12345678');
        $pdf2 = $this->createFakePdf('shipping_waybill_2.pdf', 'Waybill Fedex #2 TRK-87654321');

        $response = $this->actingAs($this->user)->post(route('uploads.shipping.store'), [
            'batch_name' => 'Carrier Waybills Batch Nov',
            'carrier_name' => 'FedEx Express',
            'shipping_files' => [$pdf1, $pdf2],
        ]);

        $batch = DocumentBatch::where('batch_name', 'Carrier Waybills Batch Nov')->first();
        $this->assertNotNull($batch);
        $response->assertRedirect(route('batches.show', $batch));

        $this->assertEquals(2, $batch->total_files);

        $this->assertDatabaseHas('documents', [
            'batch_id' => $batch->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'shipping_waybill_1.pdf',
        ]);
        $this->assertDatabaseHas('documents', [
            'batch_id' => $batch->id,
            'document_type' => Document::TYPE_SHIPPING_LABEL,
            'original_filename' => 'shipping_waybill_2.pdf',
        ]);

        $this->assertDatabaseHas('shipping_documents', [
            'user_id' => $this->user->id,
            'carrier_name' => 'FedEx Express',
        ]);
    }

    public function test_non_pdf_files_are_rejected_by_validation(): void
    {
        $fakeTxt = UploadedFile::fake()->create('malicious.txt', 50, 'text/plain');
        $fakePng = UploadedFile::fake()->create('image.png', 50, 'image/png');

        $response = $this->actingAs($this->user)->post(route('uploads.store'), [
            'documents' => [$fakeTxt, $fakePng],
        ]);

        $response->assertSessionHasErrors(['documents.0', 'documents.1']);
        $this->assertEquals(0, DocumentBatch::count());
    }

    public function test_duplicate_pdf_uploads_are_detected_and_prevented(): void
    {
        // Upload initial PDF
        $pdf1 = $this->createFakePdf('original_invoice.pdf', 'Fixed static content');

        $this->actingAs($this->user)->post(route('uploads.store'), [
            'batch_name' => 'Batch 1',
            'process_immediately' => '0',
            'documents' => [$pdf1],
        ]);

        $this->assertEquals(1, Document::count());
        $initialDoc = Document::first();

        // Attempt uploading the exact duplicate file again under a different name
        $pdfDuplicate = $this->createFakePdf('renamed_invoice.pdf', 'Fixed static content');

        $dupResponse = $this->actingAs($this->user)->post(route('uploads.store'), [
            'batch_name' => 'Batch 2 with Duplicate',
            'process_immediately' => '0',
            'documents' => [$pdfDuplicate],
        ]);

        // Duplicate must be skipped: total documents should remain 1
        $this->assertEquals(1, Document::count());
        $this->assertEquals($initialDoc->file_hash, Document::first()->file_hash);
    }

    public function test_user_can_view_batches_list_and_batch_details(): void
    {
        $pdf = $this->createFakePdf('test_invoice.pdf', 'Content A');
        $this->actingAs($this->user)->post(route('uploads.store'), [
            'batch_name' => 'Viewable Batch',
            'process_immediately' => '0',
            'documents' => [$pdf],
        ]);

        $batch = DocumentBatch::first();

        // Check index
        $indexRes = $this->actingAs($this->user)->get(route('batches.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('Viewable Batch');

        // Check show
        $showRes = $this->actingAs($this->user)->get(route('batches.show', $batch));
        $showRes->assertOk();
        $showRes->assertSee('test_invoice.pdf');
    }

    public function test_unauthorized_user_cannot_view_or_download_another_users_document(): void
    {
        $pdf = $this->createFakePdf('secret_inv.pdf', 'Sensitive financial data');
        $this->actingAs($this->user)->post(route('uploads.store'), [
            'batch_name' => 'Private User Batch',
            'process_immediately' => '0',
            'documents' => [$pdf],
        ]);

        $document = Document::first();

        // Other regular user cannot download
        $downloadRes = $this->actingAs($this->otherUser)->get(route('documents.download', $document));
        $downloadRes->assertForbidden();

        // Other regular user cannot preview
        $previewRes = $this->actingAs($this->otherUser)->get(route('documents.preview', $document));
        $previewRes->assertForbidden();

        // Owner can download
        $ownerDownload = $this->actingAs($this->user)->get(route('documents.download', $document));
        $ownerDownload->assertOk();
        $this->assertEquals('application/pdf', $ownerDownload->headers->get('content-type'));

        // Admin can download (global audit permission)
        $adminDownload = $this->actingAs($this->admin)->get(route('documents.download', $document));
        $adminDownload->assertOk();
    }

    public function test_ajax_upload_returns_structured_json_with_batch_and_duplicate_info(): void
    {
        $pdf1 = $this->createFakePdf('ajax_inv1.pdf', 'Ajax Inv 1');
        $pdf2 = $this->createFakePdf('ajax_inv2.pdf', 'Ajax Inv 2');

        $response = $this->actingAs($this->user)->postJson(route('uploads.store'), [
            'batch_name' => 'AJAX Ingestion Batch',
            'process_immediately' => '0',
            'documents' => [$pdf1, $pdf2],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'batch_id',
            'batch_name',
            'uploaded_count',
            'total_pages',
            'duplicates',
            'errors',
            'redirect_url',
        ]);
        $response->assertJson([
            'success' => true,
            'uploaded_count' => 2,
        ]);
    }

    public function test_user_can_delete_document_and_file_is_removed_from_storage(): void
    {
        $pdf = $this->createFakePdf('to_delete.pdf', 'Disposable doc');
        $this->actingAs($this->user)->post(route('uploads.store'), [
            'batch_name' => 'Temp Batch',
            'process_immediately' => '0',
            'documents' => [$pdf],
        ]);

        $doc = Document::first();
        $storedPath = $doc->stored_path;
        $this->assertTrue(Storage::disk('local')->exists($storedPath));

        $deleteRes = $this->actingAs($this->user)->delete(route('documents.destroy', $doc));
        $deleteRes->assertRedirect();

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
        $this->assertFalse(Storage::disk('local')->exists($storedPath));
    }
}
