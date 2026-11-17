<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();

        // 1. Base Query with User Scoping & Date Range Filtering
        $baseQuery = Invoice::query()->when($userId, fn ($q) => $q->where('user_id', $userId));

        if ($request->filled('start_date')) {
            $baseQuery->whereDate('invoice_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $baseQuery->whereDate('invoice_date', '<=', $request->end_date);
        }
        if ($request->filled('matching_status')) {
            $baseQuery->where('matching_status', $request->matching_status);
        }

        // 2. High-Level Aggregated KPI Metrics
        $totalInvoices = (clone $baseQuery)->count();
        $totalInvoiceValue = (float) (clone $baseQuery)->sum('total_amount');
        $totalTaxableValue = (float) (clone $baseQuery)->sum('subtotal');
        $totalCgst = (float) (clone $baseQuery)->sum('cgst_amount');
        $totalSgst = (float) (clone $baseQuery)->sum('sgst_amount');
        $totalIgst = (float) (clone $baseQuery)->sum('igst_amount');
        $totalTaxAmount = (float) (clone $baseQuery)->sum('tax_amount');
        $totalMatchedShipments = (clone $baseQuery)->where('matching_status', 'matched')->count();
        $totalUnmatchedShipments = (clone $baseQuery)->whereIn('matching_status', ['unmatched', 'mismatch', 'review_needed'])->count();

        // Database Driver Aware Expressions for SQLite & MySQL
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', coalesce(invoice_date, created_at)) as report_date"
            : 'DATE(coalesce(invoice_date, created_at)) as report_date';

        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', coalesce(invoice_date, created_at)) as report_month"
            : 'DATE_FORMAT(coalesce(invoice_date, created_at), "%Y-%m") as report_month';

        // 3. Date-Wise Invoice Reports (Paginated)
        $dateReports = (clone $baseQuery)
            ->select(
                DB::raw($dateExpr),
                DB::raw('COUNT(*) as invoices_count'),
                DB::raw('SUM(total_amount) as total_value'),
                DB::raw('SUM(subtotal) as taxable_value'),
                DB::raw('SUM(cgst_amount) as total_cgst'),
                DB::raw('SUM(sgst_amount) as total_sgst'),
                DB::raw('SUM(igst_amount) as total_igst'),
                DB::raw("SUM(CASE WHEN matching_status = 'matched' THEN 1 ELSE 0 END) as matched_count"),
                DB::raw("SUM(CASE WHEN matching_status != 'matched' THEN 1 ELSE 0 END) as unmatched_count")
            )
            ->groupBy('report_date')
            ->orderBy('report_date', 'desc')
            ->paginate(15, ['*'], 'date_page')
            ->withQueryString();

        // 4. Monthly Invoice Reports
        $monthlyReports = (clone $baseQuery)
            ->select(
                DB::raw($monthExpr),
                DB::raw('COUNT(*) as invoices_count'),
                DB::raw('SUM(total_amount) as total_value'),
                DB::raw('SUM(subtotal) as taxable_value'),
                DB::raw('SUM(cgst_amount) as total_cgst'),
                DB::raw('SUM(sgst_amount) as total_sgst'),
                DB::raw('SUM(igst_amount) as total_igst'),
                DB::raw('AVG(ai_confidence_score) as avg_confidence'),
                DB::raw("SUM(CASE WHEN matching_status = 'matched' THEN 1 ELSE 0 END) as matched_count"),
                DB::raw("SUM(CASE WHEN matching_status != 'matched' THEN 1 ELSE 0 END) as unmatched_count")
            )
            ->groupBy('report_month')
            ->orderBy('report_month', 'desc')
            ->take(12)
            ->get();

        // 5. Vendor Spend Breakdown
        $vendorSpend = Vendor::when($userId, function ($q) use ($userId) {
                $q->whereHas('invoices', fn ($iq) => $iq->where('user_id', $userId));
            })
            ->withCount(['invoices' => function ($q) use ($userId) {
                $q->when($userId, fn ($sub) => $sub->where('user_id', $userId));
            }])
            ->withSum(['invoices' => function ($q) use ($userId) {
                $q->when($userId, fn ($sub) => $sub->where('user_id', $userId));
            }], 'total_amount')
            ->orderByDesc('invoices_sum_total_amount')
            ->take(8)
            ->get();

        return view('reports.index', compact(
            'totalInvoices',
            'totalInvoiceValue',
            'totalTaxableValue',
            'totalCgst',
            'totalSgst',
            'totalIgst',
            'totalTaxAmount',
            'totalMatchedShipments',
            'totalUnmatchedShipments',
            'dateReports',
            'monthlyReports',
            'vendorSpend'
        ));
    }
}
