@extends('layouts.app')

@section('page_title', 'Analytics & Reports')

@section('content')
<!-- Filter & Date Range Selection Bar -->
<div class="card mb-4 border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label text-muted small mb-0 fw-semibold">From Date:</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-0 fw-semibold">To Date:</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-0 fw-semibold">Reconciliation Status:</label>
                <select name="matching_status" class="form-select form-select-sm">
                    <option value="">All Shipments</option>
                    <option value="matched" {{ request('matching_status') === 'matched' ? 'selected' : '' }}>Matched Shipments</option>
                    <option value="unmatched" {{ request('matching_status') === 'unmatched' ? 'selected' : '' }}>Unmatched Shipments</option>
                    <option value="review_needed" {{ request('matching_status') === 'review_needed' ? 'selected' : '' }}>Needs Review</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2 mt-auto">
                <button type="submit" class="btn btn-sm btn-emerald w-100">
                    <i class="bi bi-funnel me-1"></i> Apply Filter
                </button>
                <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light border" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- 8 KPI Aggregated Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important; border-top: 3px solid var(--emerald-600) !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total Invoices Processed</span>
                <i class="bi bi-receipt text-success fs-5"></i>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono">{{ number_format($totalInvoices) }}</h4>
            <div class="text-muted small mt-1">Real-time database records</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important; border-top: 3px solid #059669 !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total Invoice Value</span>
                <i class="bi bi-currency-rupee text-success fs-5"></i>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono text-success">₹{{ number_format($totalInvoiceValue, 2) }}</h4>
            <div class="text-muted small mt-1">Gross Invoiced Volume</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important; border-top: 3px solid #0284c7 !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total Taxable Value</span>
                <i class="bi bi-tag text-primary fs-5"></i>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono text-primary">₹{{ number_format($totalTaxableValue, 2) }}</h4>
            <div class="text-muted small mt-1">Pre-Tax Base Amount</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important; border-top: 3px solid #6366f1 !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total GST Collected</span>
                <i class="bi bi-calculator text-indigo fs-5" style="color: #6366f1;"></i>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono">₹{{ number_format($totalTaxAmount ?: ($totalCgst + $totalSgst + $totalIgst), 2) }}</h4>
            <div class="text-muted small mt-1">CGST + SGST + IGST Total</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total CGST</span>
                <span class="badge bg-light text-secondary border">Central</span>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono">₹{{ number_format($totalCgst, 2) }}</h4>
            <div class="text-muted small mt-1">CGST Component</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total SGST</span>
                <span class="badge bg-light text-secondary border">State</span>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono">₹{{ number_format($totalSgst, 2) }}</h4>
            <div class="text-muted small mt-1">SGST Component</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Total IGST</span>
                <span class="badge bg-light text-secondary border">Interstate</span>
            </div>
            <h4 class="fw-bold text-dark mb-0 mono">₹{{ number_format($totalIgst, 2) }}</h4>
            <div class="text-muted small mt-1">IGST Component</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important; border-top: 3px solid #10b981 !important;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small fw-semibold text-uppercase">Shipment Reconciliation</span>
                <i class="bi bi-truck text-success fs-5"></i>
            </div>
            <h4 class="fw-bold mb-0 mono">
                <span class="text-success">{{ number_format($totalMatchedShipments) }}</span> / <span class="text-warning-emphasis">{{ number_format($totalUnmatchedShipments) }}</span>
            </h4>
            <div class="text-muted small mt-1">Matched vs Unmatched AWB</div>
        </div>
    </div>
</div>

<!-- Date-Wise Invoice Reports (Paginated) -->
<div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold fs-6 text-dark">
            <i class="bi bi-calendar-event text-success me-2"></i> Date-Wise Invoice Reports
        </span>
        <span class="badge bg-light text-secondary border">Daily Rollup</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Date</th>
                    <th class="text-center">Invoices</th>
                    <th class="text-end">Total Invoice Value</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end">IGST</th>
                    <th class="text-center">Matched Shipments</th>
                    <th class="text-center">Unmatched</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dateReports as $dr)
                    <tr>
                        <td class="fw-bold text-dark text-nowrap">
                            <i class="bi bi-calendar3 text-muted me-1"></i> {{ date('d M Y', strtotime($dr->report_date)) }}
                        </td>
                        <td class="text-center mono fw-semibold">{{ $dr->invoices_count }}</td>
                        <td class="text-end mono fw-bold text-success">₹{{ number_format($dr->total_value, 2) }}</td>
                        <td class="text-end mono">₹{{ number_format($dr->taxable_value, 2) }}</td>
                        <td class="text-end mono text-secondary">₹{{ number_format($dr->total_cgst, 2) }}</td>
                        <td class="text-end mono text-secondary">₹{{ number_format($dr->total_sgst, 2) }}</td>
                        <td class="text-end mono text-secondary">₹{{ number_format($dr->total_igst, 2) }}</td>
                        <td class="text-center mono text-success fw-semibold">{{ $dr->matched_count }}</td>
                        <td class="text-center mono text-warning-emphasis fw-semibold">{{ $dr->unmatched_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No date-wise invoice records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($dateReports->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $dateReports->links() }}
        </div>
    @endif
</div>

<!-- Monthly Invoice Reports & Vendor Spend Analysis -->
<div class="row g-4 mb-4">
    <!-- Monthly Breakdown -->
    <div class="col-lg-8">
        <div class="card h-100 shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-calendar-check text-primary me-2"></i> Monthly Invoice Reports
                </span>
                <span class="badge bg-light text-muted border">Trailing 12 Months</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Invoices</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-end">Taxable</th>
                            <th class="text-end">Taxes (CGST+SGST+IGST)</th>
                            <th class="text-center">Matched</th>
                            <th class="text-center">AI Confidence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($monthlyReports as $mr)
                            <tr>
                                <td class="fw-bold text-dark text-nowrap">
                                    {{ date('F Y', strtotime($mr->report_month . '-01')) }}
                                </td>
                                <td class="text-center mono fw-semibold">{{ $mr->invoices_count }}</td>
                                <td class="text-end mono fw-bold text-dark">₹{{ number_format($mr->total_value, 2) }}</td>
                                <td class="text-end mono">₹{{ number_format($mr->taxable_value, 2) }}</td>
                                <td class="text-end mono text-success">₹{{ number_format($mr->total_cgst + $mr->total_sgst + $mr->total_igst, 2) }}</td>
                                <td class="text-center mono text-success fw-semibold">{{ $mr->matched_count }}</td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle mono">
                                        {{ number_format($mr->avg_confidence ?? 90, 0) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No monthly records available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Vendor Spend -->
    <div class="col-lg-4">
        <div class="card h-100 shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-building text-success me-2"></i> Vendor Spend Volume
                </span>
                <span class="badge bg-light text-muted border">Top Suppliers</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush small">
                    @forelse($vendorSpend as $vs)
                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <div class="fw-semibold text-dark">{{ $vs->name }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $vs->invoices_count }} Invoices</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold mono text-dark">₹{{ number_format($vs->invoices_sum_total_amount, 2) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No vendor transactions recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
