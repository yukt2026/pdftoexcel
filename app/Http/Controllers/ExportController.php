<?php

namespace App\Http\Controllers;

use App\Exports\InvoiceItemsExport;
use App\Exports\InvoicesExport;
use App\Models\DocumentBatch;
use App\Models\ExportRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Excel\ExcelExportService;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function index()
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();

        $query = ExportRecord::with('user')->latest();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $exportHistory = $query->paginate(15);

        $invoicesQuery = Invoice::query()->when($userId, fn ($q) => $q->where('user_id', $userId));
        $totalInvoices = (clone $invoicesQuery)->count();
        $matchedInvoices = (clone $invoicesQuery)->where('matching_status', 'matched')->count();
        $unmatchedInvoices = (clone $invoicesQuery)->whereIn('matching_status', ['unmatched', 'mismatch', 'review_needed'])->count();

        $itemsQuery = InvoiceItem::query()->when($userId, function ($q) use ($userId) {
            $q->whereHas('invoice', fn ($iq) => $iq->where('user_id', $userId));
        });
        $totalItems = $itemsQuery->count();

        $batches = DocumentBatch::when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->take(30)
            ->get();

        return view('exports.index', compact(
            'exportHistory',
            'totalInvoices',
            'matchedInvoices',
            'unmatchedInvoices',
            'totalItems',
            'batches'
        ));
    }

    /**
     * Master endpoint for the 2-Sheet Professional XLSX Workbook Export.
     * Supports all 6 export options:
     * 1. all
     * 2. selected
     * 3. date_range
     * 4. batch
     * 5. matched_only
     * 6. unmatched_only
     */
    public function exportWorkbook(Request $request, ExcelExportService $excelService)
    {
        $option = $request->input('option', 'all');
        $userId = auth()->user()->isAdmin() ? null : auth()->id();

        $filters = [
            'invoice_ids' => $request->input('invoice_ids', []),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'batch_id' => $request->input('batch_id'),
        ];

        try {
            $result = $excelService->exportWorkbook($option, $filters, $userId);

            if ($result instanceof ExportRecord) {
                return redirect()->route('exports.index')
                    ->with('info', "Your large export ({$result->total_records} records) is processing in the background. It will be available for download in your Export History shortly.");
            }

            return $result;
        } catch (Exception $e) {
            return back()->with('error', 'Export generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Legacy Invoices Export (redirects or streams 2-sheet workbook).
     */
    public function exportInvoices(Request $request, ExcelExportService $excelService)
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();
        return $excelService->exportWorkbook('all', [], $userId);
    }

    /**
     * Legacy Line Items Export.
     */
    public function exportItems(Request $request, ExcelExportService $excelService)
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();
        return $excelService->exportWorkbook('all', [], $userId);
    }

    /**
     * Download a previously generated export file from secure storage.
     */
    public function download(ExportRecord $export, ExcelExportService $excelService): BinaryFileResponse
    {
        if (!auth()->user()->isAdmin() && $export->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        try {
            return $excelService->downloadStoredFile($export);
        } catch (Exception $e) {
            abort(404, $e->getMessage());
        }
    }
}
