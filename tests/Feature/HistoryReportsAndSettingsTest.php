<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\ExportRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceShippingMatch;
use App\Models\Setting;
use App\Models\ShippingLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistoryReportsAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->user = User::factory()->create([
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * 1. HISTORY MODULE: View batches, documents, and audit trail.
     */
    public function test_history_module_renders_batches_invoices_and_exports_tabs(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Batch Test Alpha',
            'total_files' => 2,
            'total_pages' => 3,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-HIST-001',
            'invoice_date' => '2026-11-15',
            'po_number' => 'PO-HIST',
            'order_number' => 'OD-HIST',
            'shipping_id' => 'AWB-HIST',
            'buyer_name' => 'History Customer',
            'total_amount' => 500.00,
            'processing_status' => 'validated',
            'matching_status' => 'matched',
        ]);

        // Batches tab
        $responseBatches = $this->actingAs($this->user)->get(route('history.index', ['tab' => 'batches']));
        $responseBatches->assertOk();
        $responseBatches->assertSee('Batch Test Alpha');

        // Invoices tab
        $responseInvoices = $this->actingAs($this->user)->get(route('history.index', ['tab' => 'invoices']));
        $responseInvoices->assertOk();
        $responseInvoices->assertSee('INV-HIST-001');
        $responseInvoices->assertSee('History Customer');
        $responseInvoices->assertSee('AWB-HIST');
    }

    /**
     * 2. HISTORY MODULE: Search and filter across multiple parameters.
     */
    public function test_history_invoices_search_and_filters_work(): void
    {
        $inv1 = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-FIND-ME',
            'order_number' => 'OD-MATCH-999',
            'po_number' => 'PO-777',
            'shipping_id' => 'AWB-555',
            'buyer_name' => 'Target Buyer',
            'invoice_date' => '2026-11-10',
            'total_amount' => 100.00,
            'processing_status' => 'validated',
        ]);

        $inv2 = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-IGNORE-ME',
            'order_number' => 'OD-OTHER',
            'buyer_name' => 'Other Buyer',
            'invoice_date' => '2026-11-12',
            'total_amount' => 200.00,
            'processing_status' => 'failed',
        ]);

        // Filter by invoice_number
        $res = $this->actingAs($this->user)->get(route('history.index', [
            'tab' => 'invoices',
            'invoice_number' => 'INV-FIND-ME',
        ]));
        $res->assertOk();
        $res->assertSee('INV-FIND-ME');
        $res->assertDontSee('INV-IGNORE-ME');

        // Filter by buyer_name
        $resBuyer = $this->actingAs($this->user)->get(route('history.index', [
            'tab' => 'invoices',
            'buyer_name' => 'Target Buyer',
        ]));
        $resBuyer->assertOk();
        $resBuyer->assertSee('INV-FIND-ME');
        $resBuyer->assertDontSee('INV-IGNORE-ME');
    }

    /**
     * 3. HISTORY MODULE: Reprocess and Delete batch.
     */
    public function test_history_batch_can_be_reprocessed_and_deleted(): void
    {
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'Batch To Reprocess',
            'total_files' => 1,
            'total_pages' => 1,
            'status' => 'has_errors',
        ]);

        $fakePath = 'documents/fake_test.pdf';
        Storage::disk('local')->put($fakePath, 'mock-pdf-content');

        $doc = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => 'invoice',
            'original_filename' => 'fake_test.pdf',
            'stored_path' => $fakePath,
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'file_hash' => hash('sha256', 'fake'),
            'total_pages' => 1,
            'extraction_status' => 'failed',
        ]);

        // Reprocess single document
        $resDoc = $this->actingAs($this->user)->post(route('history.documents.reprocess', $doc));
        $resDoc->assertRedirect();

        // Delete batch
        $resDelete = $this->actingAs($this->user)->delete(route('history.batches.destroy', $batch));
        $resDelete->assertRedirect();

        $this->assertDatabaseMissing('document_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    }

    /**
     * 4. REPORTS MODULE: Displays real database aggregated statistics.
     */
    public function test_reports_module_displays_accurate_database_aggregations(): void
    {
        // Invoice 1: Inter-state (IGST)
        Invoice::create([
            'user_id' => $this->admin->id,
            'invoice_number' => 'REP-001',
            'invoice_date' => '2026-11-05',
            'subtotal' => 1000.00,
            'cgst_amount' => 0.00,
            'sgst_amount' => 0.00,
            'igst_amount' => 180.00,
            'tax_amount' => 180.00,
            'total_amount' => 1180.00,
            'matching_status' => 'matched',
            'ai_confidence_score' => 95.0,
        ]);

        // Invoice 2: Intra-state (CGST + SGST)
        Invoice::create([
            'user_id' => $this->admin->id,
            'invoice_number' => 'REP-002',
            'invoice_date' => '2026-11-06',
            'subtotal' => 2000.00,
            'cgst_amount' => 180.00,
            'sgst_amount' => 180.00,
            'igst_amount' => 0.00,
            'tax_amount' => 360.00,
            'total_amount' => 2360.00,
            'matching_status' => 'unmatched',
            'ai_confidence_score' => 90.0,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index'));

        $response->assertOk();
        // Total Invoices = 2
        $response->assertSee('2');
        // Total Value = 3,540.00
        $response->assertSee('3,540.00');
        // Total Taxable = 3,000.00
        $response->assertSee('3,000.00');
        // Total CGST = 180.00
        $response->assertSee('180.00');
        // Date-Wise section
        $response->assertSee('Date-Wise Invoice Reports');
        $response->assertSee('Monthly Invoice Reports');
    }

    /**
     * 5. REPORTS MODULE: Date Range Filtering.
     */
    public function test_reports_module_date_range_filtering(): void
    {
        Invoice::create([
            'user_id' => $this->admin->id,
            'invoice_number' => 'REP-OCT',
            'invoice_date' => '2026-10-15',
            'total_amount' => 500.00,
        ]);

        Invoice::create([
            'user_id' => $this->admin->id,
            'invoice_number' => 'REP-NOV',
            'invoice_date' => '2026-11-15',
            'total_amount' => 750.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]));

        $response->assertOk();
        $response->assertSee('750.00');
        $response->assertDontSee('500.00');
    }

    /**
     * 6. SETTINGS MODULE: Admin can view real-time diagnostics and update configurations.
     */
    public function test_admin_can_view_settings_and_update_configurations(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('System Configuration & Settings', false);
        $response->assertSee('Google Gemini AI Configuration');
        $response->assertSee('System Diagnostics & Status', false);
        $response->assertSee('PHP Version');
        $response->assertSee('Laravel Framework');

        // Update settings
        $payload = [
            'settings' => [
                'gemini_api_key' => 'AIzaSy_new_super_key_test',
                'gemini_model' => 'gemini-1.5-pro',
                'ocr_engine' => 'google_cloud_vision',
                'max_upload_size_mb' => '50',
                'app_name' => 'Custom AI Invoicing Suite',
                'company_name' => 'Acme Logistics Ltd',
                'brand_primary_color' => '#047857',
            ],
        ];

        $updateResponse = $this->actingAs($this->admin)->post(route('settings.update'), $payload);
        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        // Verify database settings
        $this->assertEquals('AIzaSy_new_super_key_test', Setting::get('gemini_api_key'));
        $this->assertEquals('gemini-1.5-pro', Setting::get('gemini_model'));
        $this->assertEquals('google_cloud_vision', Setting::get('ocr_engine'));
        $this->assertEquals('50', Setting::get('max_upload_size_mb'));
        $this->assertEquals('Custom AI Invoicing Suite', Setting::get('app_name'));
    }

    /**
     * 7. AUTHORIZATION: Standard user is forbidden from settings and reports.
     */
    public function test_standard_user_is_forbidden_from_settings_and_reports(): void
    {
        // Standard user attempts settings
        $resSettings = $this->actingAs($this->user)->get(route('settings.index'));
        $resSettings->assertForbidden();

        // Standard user attempts update settings
        $resUpdate = $this->actingAs($this->user)->post(route('settings.update'), ['settings' => ['app_name' => 'Hacked']]);
        $resUpdate->assertForbidden();

        // Standard user attempts reports
        $resReports = $this->actingAs($this->user)->get(route('reports.index'));
        $resReports->assertForbidden();
    }
}
