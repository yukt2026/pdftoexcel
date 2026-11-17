@extends('layouts.app')

@section('page_title', 'Excel XLSX Exports')

@section('content')
<!-- Header Banner -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Professional Excel XLSX Export Center</h4>
        <p class="text-muted small mb-0">Generate standardized 2-sheet workbooks formatted for accounting, ERP integration, and GST reconciliation.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="{{ route('exports.workbook') }}" method="POST">
            @csrf
            <input type="hidden" name="option" value="all">
            <button type="submit" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Quick Export All (2-Sheet XLSX)
            </button>
        </form>
    </div>
</div>

<!-- Main Instant Download Action Card -->
<div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border-left: 5px solid var(--primary-teal) !important;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle mb-2">⚡ 1-Click Fast Download</span>
                <h5 class="fw-bold text-dark mb-1">Download All Extracted Invoices into Excel</h5>
                <p class="text-secondary small mb-0">
                    Generates the complete 2-sheet Excel (.xlsx) file containing all {{ $totalInvoices }} extracted invoices with real line items, GST breakdown, and courier shipping IDs.
                </p>
            </div>
            <div>
                <form action="{{ route('exports.workbook') }}" method="POST">
                    @csrf
                    <input type="hidden" name="option" value="all">
                    <button type="submit" class="btn btn-emerald px-4 py-2 rounded-pill shadow-sm fw-bold fs-6">
                        <i class="bi bi-file-earmark-arrow-down-fill me-2 fs-5"></i> Download Excel Workbook Now
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- What does the Excel file contain? -->
<div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-body p-3 p-md-4">
        <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-info-circle text-primary"></i> Understanding Your Downloaded Excel File
        </h6>
        <div class="row g-3 small">
            <div class="col-md-6">
                <div class="p-3 rounded border bg-light h-100">
                    <strong class="text-dark d-block mb-1"><i class="bi bi-table text-success me-1"></i> Sheet 1: Invoice Items (16 Exact Columns)</strong>
                    <div class="text-muted" style="font-size: 0.78rem;">
                        One row per product item: Invoice Date, Invoice Number, PO Number, Order Number, Shipping ID, Buyer Name, Address, State, Item, Item Rate, Discount, Taxable Value, CGST, SGST, IGST, and Grand Total.
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded border bg-light h-100">
                    <strong class="text-dark d-block mb-1"><i class="bi bi-calculator text-primary me-1"></i> Sheet 2: Invoice Summary (Overview)</strong>
                    <div class="text-muted" style="font-size: 0.78rem;">
                        Summary table with 1 row per invoice giving pre-tax taxable amount, GST taxes, total invoice amount, and courier reconciliation status.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Filter Exports (Optional) -->
<div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3">
        <span class="fw-bold small text-uppercase text-secondary">
            <i class="bi bi-sliders me-1 text-primary"></i> Custom / Filtered Exports (Optional)
        </span>
    </div>
    <div class="card-body p-3 p-md-4">
        <div class="row g-3">
            <!-- Filter: Matched Only -->
            <div class="col-md-4">
                <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="fw-bold text-dark d-block mb-1"><i class="bi bi-patch-check text-success me-1"></i> Matched Invoices Only</span>
                        <p class="text-muted small mb-3">Download only invoices that have a verified Courier AWB Shipping ID.</p>
                    </div>
                    <form action="{{ route('exports.workbook') }}" method="POST">
                        @csrf
                        <input type="hidden" name="option" value="matched_only">
                        <button type="submit" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-semibold" {{ $matchedInvoices == 0 ? 'disabled' : '' }}>
                            Download Matched ({{ $matchedInvoices }})
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filter: Date Range -->
            <div class="col-md-4">
                <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="fw-bold text-dark d-block mb-1"><i class="bi bi-calendar-range text-primary me-1"></i> Filter by Date Range</span>
                        <p class="text-muted small mb-2">Export invoices within a specific date window.</p>
                    </div>
                    <form action="{{ route('exports.workbook') }}" method="POST">
                        @csrf
                        <input type="hidden" name="option" value="date_range">
                        <div class="row g-1 mb-2">
                            <div class="col-6">
                                <input type="date" name="start_date" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <input type="date" name="end_date" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-semibold">
                            Download for Dates
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filter: By Batch -->
            <div class="col-md-4">
                <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="fw-bold text-dark d-block mb-1"><i class="bi bi-folder2-open text-warning me-1"></i> Filter by Upload Batch</span>
                        <p class="text-muted small mb-2">Export only invoices from one specific upload session.</p>
                    </div>
                    <form action="{{ route('exports.workbook') }}" method="POST">
                        @csrf
                        <input type="hidden" name="option" value="batch">
                        <div class="mb-2">
                            <select name="batch_id" class="form-select form-select-sm" required>
                                <option value="">Select a batch...</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}">{{ $batch->batch_name }} ({{ $batch->total_files }} files)</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100 rounded-pill fw-semibold" {{ $batches->isEmpty() ? 'disabled' : '' }}>
                            Download Batch Excel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Activity History Table with Secure Download Links -->
<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold text-dark small text-uppercase d-block">
                <i class="bi bi-clock-history me-1 text-success"></i> Download Previously Generated Excel Files (History)
            </span>
            <span class="text-muted" style="font-size: 0.72rem;">Whenever you click download, a copy is saved here so you can re-download anytime without re-processing.</span>
        </div>
        <span class="badge bg-light text-muted border">Auto-Saved Copies</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>File / Export Name</th>
                    <th>Format</th>
                    <th>Records Count</th>
                    <th>Generated By</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th class="text-end text-nowrap" style="min-width: 160px; width: 170px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exportHistory as $export)
                    <tr>
                        <td class="fw-semibold text-dark">
                            <i class="bi bi-file-earmark-excel text-success me-1"></i> {{ $export->export_name }}
                        </td>
                        <td><span class="badge bg-success-subtle text-success text-uppercase">{{ $export->format }}</span></td>
                        <td class="mono fw-semibold">{{ $export->total_records }}</td>
                        <td>{{ $export->user?->name ?? 'System' }}</td>
                        <td class="text-muted">{{ $export->created_at->format('M d, Y H:i:s') }}</td>
                        <td><x-badge-status :status="$export->status" /></td>
                        <td class="text-end text-nowrap">
                            @if($export->status === 'completed')
                                <a href="{{ route('exports.download', $export) }}" class="btn-action-download">
                                    <i class="bi bi-cloud-arrow-down-fill"></i> Download Again
                                </a>
                            @else
                                <span class="badge bg-light text-muted border px-2.5 py-1">In Progress</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 text-secondary d-block mb-1"></i>
                            No past export downloads recorded yet. Click the green <strong>"Download Excel Workbook Now"</strong> button above to download your first Excel file.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($exportHistory->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $exportHistory->links() }}
        </div>
    @endif
</div>
@endsection
