<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ExportRecord;
use App\Models\Invoice;
use App\Models\ShippingDocument;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->isAdmin();

        // 8 Dashboard Widgets (Scoped to user if not admin)
        $invoicesQuery = Invoice::query();
        $docsQuery = Document::query();
        $shippingQuery = ShippingDocument::query();
        $exportsQuery = ExportRecord::query();

        if (!$isAdmin) {
            $invoicesQuery->where('user_id', $userId);
            $docsQuery->where('user_id', $userId);
            $shippingQuery->where('user_id', $userId);
            $exportsQuery->where('user_id', $userId);
        }

        $totalInvoices = (clone $invoicesQuery)->count();
        $totalPdfsUploaded = (clone $docsQuery)->where(function ($q) {
            $q->where('file_type', 'pdf')
              ->orWhere('original_filename', 'like', '%.pdf');
        })->count();

        $processedInvoices = (clone $invoicesQuery)->whereIn('processing_status', ['extracted', 'validated', 'exported'])->count();
        $pendingInvoices = (clone $docsQuery)->whereIn('status', ['uploaded', 'parsing'])->count() + 
                           (clone $invoicesQuery)->whereIn('processing_status', ['pending', 'processing'])->count();
        
        $failedInvoices = (clone $docsQuery)->where('status', 'failed')->count() + 
                          (clone $invoicesQuery)->where('processing_status', 'failed')->count();

        $matchedShippingLabels = (clone $shippingQuery)->whereIn('status', ['matched', 'delivered'])->count();
        
        $unmatchedInvoices = (clone $invoicesQuery)->whereIn('matching_status', ['unmatched', 'mismatch'])->count();
        
        $totalExcelExports = (clone $exportsQuery)->count();

        // Recent Upload Table data (Scoped)
        $recentUploads = (clone $docsQuery)->with(['user', 'invoice'])
            ->latest()
            ->take(8)
            ->get();

        $totalSpend = (clone $invoicesQuery)->sum('total_amount');
        $avgConfidence = (clone $invoicesQuery)->whereNotNull('ai_confidence_score')->avg('ai_confidence_score') ?: 0;

        return view('dashboard.index', compact(
            'totalInvoices',
            'totalPdfsUploaded',
            'processedInvoices',
            'pendingInvoices',
            'failedInvoices',
            'matchedShippingLabels',
            'unmatchedInvoices',
            'totalExcelExports',
            'recentUploads',
            'totalSpend',
            'avgConfidence'
        ));
    }
}
