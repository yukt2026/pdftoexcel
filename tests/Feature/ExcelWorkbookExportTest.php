<?php

namespace Tests\Feature;

use App\Exports\InvoiceWorkbookExport;
use App\Exports\Sheets\InvoiceItemsSheet;
use App\Exports\Sheets\InvoiceSummarySheet;
use App\Jobs\ExportInvoicesJob;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\ExportRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceShippingMatch;
use App\Models\ShippingLabel;
use App\Models\User;
use App\Services\Excel\ExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExcelWorkbookExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create([
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * 1. WORKBOOK STRUCTURE: 2 sheets with exact names.
     */
    public function test_workbook_contains_exactly_two_sheets_with_correct_names(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-2026-001',
            'invoice_date' => '2026-11-15',
            'po_number' => 'PO-100',
            'order_number' => 'OD-100',
            'shipping_id' => 'AWB-100',
            'buyer_name' => 'Alice Kumar',
            'buyer_address' => '123 MG Road, Bengaluru',
            'buyer_state' => 'Karnataka',
            'currency' => 'INR',
            'subtotal' => 1000.00,
            'cgst_amount' => 90.00,
            'sgst_amount' => 90.00,
            'total_amount' => 1180.00,
            'matching_status' => 'matched',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item 1',
            'quantity' => 1,
            'item_rate' => 1000.00,
            'taxable_value' => 1000.00,
            'cgst' => 90.00,
            'sgst' => 90.00,
            'total_price' => 1180.00,
        ]);

        $workbook = new InvoiceWorkbookExport(collect([$invoice]));
        $sheets = $workbook->sheets();

        $this->assertCount(2, $sheets);
        $this->assertInstanceOf(InvoiceItemsSheet::class, $sheets[0]);
        $this->assertInstanceOf(InvoiceSummarySheet::class, $sheets[1]);
        $this->assertEquals('Invoice Items', $sheets[0]->title());
        $this->assertEquals('Invoice Summary', $sheets[1]->title());
    }

    /**
     * 2. SHEET 1: INVOICE ITEMS exact 16 columns in exact order.
     */
    public function test_sheet_1_has_exact_16_columns_in_exact_order(): void
    {
        $sheet = new InvoiceItemsSheet(collect([]));
        $headings = $sheet->headings();

        $expectedColumns = [
            'Invoice Date',
            'Invoice Number',
            'PO Number',
            'Order Number',
            'Shipping ID',
            'Buyer Name',
            'Address',
            'State',
            'Item',
            'Item Rate',
            'Discount',
            'Taxable Value',
            'CGST',
            'SGST',
            'IGST',
            'Total',
        ];

        $this->assertEquals($expectedColumns, $headings);
        $this->assertCount(16, $headings);
    }

    /**
     * 3. SHEET 1 DATA RULES:
     * - Repeat invoice information for each product.
     * - Total appears ONLY on the first product row for that invoice.
     * - Item rate contains Gross Amount.
     * - Missing tax components are 0.
     */
    public function test_sheet_1_data_rules_repeat_invoice_fields_and_total_only_on_first_row(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-MULTI-01',
            'invoice_date' => '2026-11-18',
            'po_number' => 'PO-999',
            'order_number' => 'OD-888',
            'shipping_id' => 'AWB-777',
            'buyer_name' => 'John Doe',
            'buyer_address' => '742 Evergreen Terrace',
            'buyer_state' => 'Maharashtra',
            'currency' => 'INR',
            'subtotal' => 2500.00,
            'cgst_amount' => 225.00,
            'sgst_amount' => 225.00,
            'igst_amount' => 0.00,
            'total_amount' => 2950.00,
            'matching_status' => 'matched',
        ]);

        // Product 1
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'First Product A',
            'quantity' => 1,
            'item_rate' => 1500.00,
            'discount' => 100.00,
            'taxable_value' => 1400.00,
            'cgst' => 126.00,
            'sgst' => 126.00,
            'igst' => 0.00,
            'total_price' => 1652.00,
        ]);

        // Product 2
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Second Product B',
            'quantity' => 1,
            'item_rate' => 1100.00,
            'discount' => 0.00,
            'taxable_value' => 1100.00,
            'cgst' => 99.00,
            'sgst' => 99.00,
            'igst' => 0.00,
            'total_price' => 1298.00,
        ]);

        $sheet = new InvoiceItemsSheet(collect([$invoice]));
        $rows = $sheet->array();

        $this->assertCount(2, $rows);

        // Row 1 Assertions:
        $row1 = $rows[0];
        $this->assertEquals('2026-11-18', $row1[0]); // Invoice Date
        $this->assertEquals('INV-MULTI-01', $row1[1]); // Invoice Number
        $this->assertEquals('PO-999', $row1[2]); // PO Number
        $this->assertEquals('OD-888', $row1[3]); // Order Number
        $this->assertEquals('AWB-777', $row1[4]); // Shipping ID
        $this->assertEquals('John Doe', $row1[5]); // Buyer Name
        $this->assertEquals('742 Evergreen Terrace', $row1[6]); // Address
        $this->assertEquals('Maharashtra', $row1[7]); // State
        $this->assertEquals('First Product A', $row1[8]); // Item
        $this->assertEquals(1500.00, $row1[9]); // Item Rate
        $this->assertEquals(100.00, $row1[10]); // Discount
        $this->assertEquals(1400.00, $row1[11]); // Taxable Value
        $this->assertEquals(126.00, $row1[12]); // CGST
        $this->assertEquals(126.00, $row1[13]); // SGST
        $this->assertEquals(0.00, $row1[14]); // IGST (0 when missing)
        $this->assertEquals(2950.00, $row1[15]); // Total on FIRST row

        // Row 2 Assertions:
        $row2 = $rows[1];
        // Repeated invoice level data
        $this->assertEquals('2026-11-18', $row2[0]);
        $this->assertEquals('INV-MULTI-01', $row2[1]);
        $this->assertEquals('PO-999', $row2[2]);
        $this->assertEquals('OD-888', $row2[3]);
        $this->assertEquals('AWB-777', $row2[4]);
        $this->assertEquals('John Doe', $row2[5]);
        $this->assertEquals('742 Evergreen Terrace', $row2[6]);
        $this->assertEquals('Maharashtra', $row2[7]);
        $this->assertEquals('Second Product B', $row2[8]);
        $this->assertEquals(1100.00, $row2[9]);
        // CRITICAL RULE: Total must be BLANK on subsequent rows of the same invoice
        $this->assertEquals('', $row2[15]);
    }

    /**
     * 4. SHIPPING ID: Resolved from matched shipping label.
     */
    public function test_sheet_1_shipping_id_resolves_from_matched_shipping_label(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-EKART-01',
            'order_number' => 'OD439142473682225100',
            'total_amount' => 500.00,
            'matching_status' => 'matched',
            'shipping_id' => null, // Not directly set on invoice
        ]);

        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'order_number' => 'OD439142473682225100',
            'shipping_id' => 'FMPC6756481216',
            'awb_number' => 'FMPC6756481216',
            'status' => 'matched',
        ]);

        InvoiceShippingMatch::create([
            'user_id' => $this->user->id,
            'invoice_id' => $invoice->id,
            'shipping_label_id' => $label->id,
            'order_number' => 'OD439142473682225100',
            'awb_number' => 'FMPC6756481216',
            'status' => 'matched',
        ]);

        $invoice->load('shippingMatch.shippingLabel');

        $sheet = new InvoiceItemsSheet(collect([$invoice]));
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $this->assertEquals('FMPC6756481216', $rows[0][4]); // Column 5: Shipping ID
    }

    /**
     * 5. SHEET 2: INVOICE SUMMARY exact columns and row mapping.
     */
    public function test_sheet_2_invoice_summary_columns_and_data_mapping(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-SUM-001',
            'invoice_date' => '2026-11-12',
            'po_number' => 'PO-351974314',
            'order_number' => 'OD-351974314',
            'shipping_id' => '1490843814423655',
            'buyer_name' => 'Meera Nair',
            'buyer_state' => 'Kerala',
            'currency' => 'INR',
            'subtotal' => 800.00,
            'cgst_amount' => 40.00,
            'sgst_amount' => 40.00,
            'igst_amount' => 0.00,
            'total_amount' => 880.00,
            'matching_status' => 'matched',
        ]);

        $sheet = new InvoiceSummarySheet(collect([$invoice]));

        $expectedHeadings = [
            'Invoice Date',
            'Invoice Number',
            'PO Number',
            'Order Number',
            'Shipping ID',
            'Buyer Name',
            'State',
            'Total Taxable Value',
            'Total CGST',
            'Total SGST',
            'Total IGST',
            'Invoice Total',
            'Matching Status',
        ];

        $this->assertEquals($expectedHeadings, $sheet->headings());
        $this->assertCount(13, $sheet->headings());

        $rows = $sheet->array();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertEquals('2026-11-12', $row[0]);
        $this->assertEquals('INV-SUM-001', $row[1]);
        $this->assertEquals('PO-351974314', $row[2]);
        $this->assertEquals('OD-351974314', $row[3]);
        $this->assertEquals('1490843814423655', $row[4]);
        $this->assertEquals('Meera Nair', $row[5]);
        $this->assertEquals('Kerala', $row[6]);
        $this->assertEquals(800.00, $row[7]);
        $this->assertEquals(40.00, $row[8]);
        $this->assertEquals(40.00, $row[9]);
        $this->assertEquals(0.00, $row[10]);
        $this->assertEquals(880.00, $row[11]);
        $this->assertEquals('Matched', $row[12]);
    }

    /**
     * 6. EXPORT OPTIONS:
     * Option 1: All
     * Option 2: Selected
     * Option 3: Date Range
     * Option 4: Batch
     * Option 5: Matched Only
     * Option 6: Unmatched Separately
     */
    public function test_all_six_export_options_filter_properly(): void
    {
        $excelService = app(ExcelExportService::class);

        // Batch setup
        $batch = DocumentBatch::create([
            'user_id' => $this->user->id,
            'batch_name' => 'November Batch Alpha',
            'total_files' => 1,
            'total_pages' => 1,
            'status' => 'completed',
        ]);

        $doc = Document::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'document_type' => 'invoice',
            'original_filename' => 'batch_doc.pdf',
            'stored_path' => 'documents/batch_doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'file_hash' => hash('sha256', 'batch_doc'),
            'total_pages' => 1,
        ]);

        // Invoice 1: Matched, in batch, date 2026-11-01
        $inv1 = Invoice::create([
            'user_id' => $this->user->id,
            'document_id' => $doc->id,
            'invoice_number' => 'INV-OPT-1',
            'invoice_date' => '2026-11-01',
            'total_amount' => 100.00,
            'matching_status' => 'matched',
        ]);

        // Invoice 2: Unmatched, date 2026-11-15
        $inv2 = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-OPT-2',
            'invoice_date' => '2026-11-15',
            'total_amount' => 200.00,
            'matching_status' => 'unmatched',
        ]);

        // Invoice 3: Matched, date 2026-11-20
        $inv3 = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-OPT-3',
            'invoice_date' => '2026-11-20',
            'total_amount' => 300.00,
            'matching_status' => 'matched',
        ]);

        // 1. All
        $qAll = $excelService->buildQuery('all', [], $this->user->id);
        $this->assertEquals(3, $qAll->count());

        // 2. Selected
        $qSelected = $excelService->buildQuery('selected', ['invoice_ids' => [$inv1->id, $inv3->id]], $this->user->id);
        $this->assertEquals(2, $qSelected->count());
        $this->assertEquals([$inv3->id, $inv1->id], $qSelected->pluck('id')->sortDesc()->values()->toArray());

        // 3. Date Range (2026-11-10 to 2026-11-18)
        $qDate = $excelService->buildQuery('date_range', ['start_date' => '2026-11-10', 'end_date' => '2026-11-18'], $this->user->id);
        $this->assertEquals(1, $qDate->count());
        $this->assertEquals($inv2->id, $qDate->first()->id);

        // 4. Batch
        $qBatch = $excelService->buildQuery('batch', ['batch_id' => $batch->id], $this->user->id);
        $this->assertEquals(1, $qBatch->count());
        $this->assertEquals($inv1->id, $qBatch->first()->id);

        // 5. Matched Only
        $qMatched = $excelService->buildQuery('matched_only', [], $this->user->id);
        $this->assertEquals(2, $qMatched->count());

        // 6. Unmatched Only
        $qUnmatched = $excelService->buildQuery('unmatched_only', [], $this->user->id);
        $this->assertEquals(1, $qUnmatched->count());
        $this->assertEquals($inv2->id, $qUnmatched->first()->id);
    }

    /**
     * 7. CONTROLLER ENDPOINTS & EXPORT RECORD GENERATION:
     * POST /exports/workbook triggers download and logs ExportRecord.
     */
    public function test_export_workbook_controller_endpoint_streams_download_and_logs_record(): void
    {
        Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-WEB-001',
            'invoice_date' => '2026-11-10',
            'total_amount' => 500.00,
            'matching_status' => 'matched',
        ]);

        $response = $this->actingAs($this->user)->post(route('exports.workbook'), [
            'option' => 'all',
        ]);

        $response->assertOk();
        $this->assertTrue($response->headers->get('content-disposition') !== null);
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        // Verify ExportRecord created
        $this->assertDatabaseHas('export_records', [
            'user_id' => $this->user->id,
            'format' => 'xlsx',
            'status' => 'completed',
        ]);
    }

    /**
     * 8. SECURE STORAGE & DOWNLOAD: User can download past export file.
     */
    public function test_user_can_download_stored_export_file(): void
    {
        $fakePath = 'exports/test_stored_export.xlsx';
        Storage::disk('local')->put($fakePath, 'mock-xlsx-binary-content');

        $record = ExportRecord::create([
            'user_id' => $this->user->id,
            'export_name' => 'Test Historical Export',
            'format' => 'xlsx',
            'file_path' => $fakePath,
            'total_records' => 10,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('exports.download', $record));

        $response->assertOk();
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    /**
     * 9. AUTHORIZATION: Standard user cannot download another user's export.
     */
    public function test_unauthorized_user_cannot_download_other_users_export(): void
    {
        $otherUser = User::factory()->create(['role' => User::ROLE_USER]);

        $fakePath = 'exports/secret_export.xlsx';
        Storage::disk('local')->put($fakePath, 'secret-data');

        $record = ExportRecord::create([
            'user_id' => $otherUser->id,
            'export_name' => 'Secret Export',
            'format' => 'xlsx',
            'file_path' => $fakePath,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('exports.download', $record));
        $response->assertForbidden();
    }

    /**
     * 10. BACKGROUND QUEUE PROCESSING:
     * ExportInvoicesJob runs and completes large export tasks.
     */
    public function test_export_invoices_job_runs_and_stores_workbook(): void
    {
        $inv = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-JOB-001',
            'total_amount' => 1200.00,
            'matching_status' => 'matched',
        ]);

        $record = ExportRecord::create([
            'user_id' => $this->user->id,
            'export_name' => 'Queued Large Export',
            'format' => 'xlsx',
            'file_path' => 'exports/queued_test.xlsx',
            'status' => 'processing',
        ]);

        $job = new ExportInvoicesJob($record, [$inv->id], 'queued_test.xlsx');
        $job->handle();

        $record->refresh();
        $this->assertEquals('completed', $record->status);
        $this->assertEquals(1, $record->total_records);
        $this->assertTrue(Storage::disk('local')->exists('exports/queued_test.xlsx'));
    }

    /**
     * 11. SPREADSHEET PHYSICAL INSPECTION:
     * Validates freeze panes, autofilter, and sheet formatting using PhpSpreadsheet.
     */
    public function test_physical_xlsx_file_contains_proper_freeze_pane_and_autofilter(): void
    {
        $inv = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-INSPECT-001',
            'invoice_date' => '2026-11-18',
            'order_number' => 'OD-INSPECT',
            'shipping_id' => 'AWB-INSPECT',
            'buyer_name' => 'Spreadsheet Validator',
            'currency' => 'INR',
            'total_amount' => 1500.00,
            'matching_status' => 'matched',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'description' => 'Inspected Item',
            'item_rate' => 1500.00,
            'total_price' => 1500.00,
        ]);

        $filePath = 'exports/physical_inspect.xlsx';
        Excel::store(new InvoiceWorkbookExport(collect([$inv])), $filePath, 'local');

        $fullPath = Storage::disk('local')->path($filePath);
        $this->assertFileExists($fullPath);

        $spreadsheet = IOFactory::load($fullPath);

        $this->assertEquals(2, $spreadsheet->getSheetCount());

        // Sheet 1: Invoice Items
        $sheet1 = $spreadsheet->getSheet(0);
        $this->assertEquals('Invoice Items', $sheet1->getTitle());
        $this->assertEquals('A2', $sheet1->getFreezePane());
        $this->assertNotEmpty($sheet1->getAutoFilter()->getRange());
        $this->assertEquals('Invoice Date', $sheet1->getCell('A1')->getValue());
        $this->assertEquals('Total', $sheet1->getCell('P1')->getValue());

        // Sheet 2: Invoice Summary
        $sheet2 = $spreadsheet->getSheet(1);
        $this->assertEquals('Invoice Summary', $sheet2->getTitle());
        $this->assertEquals('A2', $sheet2->getFreezePane());
        $this->assertNotEmpty($sheet2->getAutoFilter()->getRange());
        $this->assertEquals('Invoice Date', $sheet2->getCell('A1')->getValue());
        $this->assertEquals('Matching Status', $sheet2->getCell('M1')->getValue());
    }
}
