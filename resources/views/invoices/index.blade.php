@extends('layouts.app')

@section('page_title', 'All Invoices')

@section('content')
<!-- Filter Bar -->
<div class="card mb-4 border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search invoice #, PO, vendor..." value="{{ request('search') }}">
                </div>
            </div>

            <div class="col-md-2">
                <select name="processing_status" class="form-select form-select-sm">
                    <option value="">All Processing Status</option>
                    <option value="extracted" {{ request('processing_status') == 'extracted' ? 'selected' : '' }}>Extracted</option>
                    <option value="validated" {{ request('processing_status') == 'validated' ? 'selected' : '' }}>Validated</option>
                    <option value="review_needed" {{ request('processing_status') == 'review_needed' ? 'selected' : '' }}>Review Needed</option>
                    <option value="failed" {{ request('processing_status') == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="matching_status" class="form-select form-select-sm">
                    <option value="">All Match Status</option>
                    <option value="matched" {{ request('matching_status') == 'matched' ? 'selected' : '' }}>Matched</option>
                    <option value="unmatched" {{ request('matching_status') == 'unmatched' ? 'selected' : '' }}>Unmatched</option>
                    <option value="mismatch" {{ request('matching_status') == 'mismatch' ? 'selected' : '' }}>Mismatch</option>
                </select>
            </div>

            <div class="col-md-3">
                <select name="vendor_id" class="form-select form-select-sm">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}" {{ request('vendor_id') == $vendor->id ? 'selected' : '' }}>
                            {{ $vendor->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-emerald w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-light border" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Invoices Master Table Card -->
<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold fs-6 text-dark">
            <i class="bi bi-receipt text-success me-2"></i> Invoices Master List ({{ $invoices->total() }})
        </span>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="bulkExportForm" class="btn btn-sm btn-outline-success rounded-pill px-3" id="exportSelectedBtn">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Selected (.xlsx)
            </button>
            <a href="{{ route('exports.index') }}" class="btn btn-sm btn-light border rounded-pill px-3">
                <i class="bi bi-sliders me-1"></i> Export Center
            </a>
            <a href="{{ route('uploads.invoices') }}" class="btn btn-sm btn-emerald rounded-pill px-3 shadow-sm">
                <i class="bi bi-cloud-arrow-up me-1"></i> Upload Invoices
            </a>
        </div>
    </div>

    <form id="bulkExportForm" action="{{ route('exports.workbook') }}" method="POST">
        @csrf
        <input type="hidden" name="option" value="selected">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th style="width: 35px;">
                            <input type="checkbox" class="form-check-input" id="selectAllInvoices" title="Select all on this page">
                        </th>
                        <th>Invoice Date</th>
                        <th>Invoice Number</th>
                        <th>PO Number</th>
                        <th>Order Number</th>
                        <th>Shipping ID (AWB)</th>
                        <th>Buyer Name</th>
                        <th>State</th>
                        <th class="text-end">Total</th>
                        <th>Processing Status</th>
                        <th class="text-end text-nowrap" style="min-width: 110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $resolvedShippingId = $invoice->resolved_shipping_id;
                            $validation = $invoice->validation_issues;
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="form-check-input invoice-select-checkbox">
                            </td>
                        <td class="text-nowrap text-secondary">
                            <i class="bi bi-calendar3 me-1 text-muted"></i>
                            {{ $invoice->invoice_date?->format('d M Y') ?? '-' }}
                        </td>
                        <td class="fw-semibold">
                            <a href="{{ route('invoices.show', $invoice) }}" class="text-decoration-none" style="color: var(--emerald-700);">
                                {{ $invoice->invoice_number ?? 'Draft' }}
                            </a>
                            @if($invoice->is_manually_edited)
                                <span class="badge bg-light text-primary border ms-1" style="font-size: 0.65rem;" title="Manually edited">
                                    <i class="bi bi-pencil-fill"></i> Edited
                                </span>
                            @endif
                            @if($validation['is_possible_duplicate'])
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size: 0.65rem;" title="Possible duplicate invoice number detected">
                                    <i class="bi bi-copy"></i> Duplicate
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($invoice->po_number)
                                <span class="badge bg-light text-dark border">{{ $invoice->po_number }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($invoice->order_number)
                                <span class="fw-medium text-dark mono">{{ $invoice->order_number }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($resolvedShippingId)
                                <span class="badge bg-emerald-subtle text-emerald-800 border border-emerald-subtle mono">
                                    <i class="bi bi-truck me-1"></i> {{ $resolvedShippingId }}
                                </span>
                            @else
                                <span class="badge bg-light text-muted border">
                                    <i class="bi bi-dash-circle me-1"></i> Unassigned
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-medium text-dark text-truncate" style="max-width: 140px;" title="{{ $invoice->buyer_name ?? $invoice->customer?->name }}">
                                {{ $invoice->buyer_name ?? $invoice->customer?->name ?? 'N/A' }}
                            </div>
                        </td>
                        <td>
                            @php
                                $stateDisplay = $invoice->buyer_state ?: $invoice->place_of_supply;
                            @endphp
                            @if($stateDisplay)
                                <span class="badge bg-light text-secondary border">{{ $stateDisplay }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end fw-bold mono text-dark text-nowrap">
                            ₹{{ number_format($invoice->total_amount, 2) }}
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <div><x-badge-status :status="$invoice->processing_status" /></div>
                                @if($validation['has_tax_discrepancy'])
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;" title="Tax calculation discrepancy detected">
                                        <i class="bi bi-calculator"></i> Math Discrepancy
                                    </span>
                                @endif
                                @if($validation['is_low_confidence'])
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;" title="AI Confidence below 85%">
                                        <i class="bi bi-exclamation-triangle"></i> Low Conf ({{ number_format($invoice->ai_confidence_score, 0) }}%)
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-light border p-1 px-2" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-light border p-1 px-2" title="Edit Fields">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="{{ route('invoices.export', $invoice) }}" class="btn btn-light border text-success p-1 px-2" title="Download Excel">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                            No invoices found matching your filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </form>

    @if($invoices->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $invoices->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllInvoices');
        const checkboxes = document.querySelectorAll('.invoice-select-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
            });
        }
    });
</script>
@endpush
