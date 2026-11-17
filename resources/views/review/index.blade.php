@extends('layouts.app')

@section('page_title', 'Data Review Queue')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Invoice Data Review & Validation</h4>
        <p class="text-muted small mb-0">Inspect AI extraction results, verify line items and math integrity before finalizing for Excel export.</p>
    </div>
    <div>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-2 px-3 rounded-pill fw-semibold">
            <i class="bi bi-hourglass-split me-1"></i> {{ $needsReviewCount }} Invoices Awaiting Review
        </span>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('invoices.review') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by invoice number or vendor..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-emerald w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    @forelse($pendingReviews as $invoice)
        @php
            $validation = $invoice->validation_issues;
            $resolvedShippingId = $invoice->resolved_shipping_id;
        @endphp
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-bold text-dark text-decoration-none">
                            {{ $invoice->invoice_number ?? 'Draft Invoice' }}
                        </a>
                        <div class="text-muted small" style="font-size: 0.72rem;">{{ $invoice->created_at->format('M d, Y H:i') }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <x-badge-status :status="$invoice->processing_status" />
                        @if($invoice->is_manually_edited)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">Edited</span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-3 small">
                    <div class="mb-2">
                        <span class="text-muted">Buyer / Customer:</span>
                        <div class="fw-semibold text-dark text-truncate">{{ $invoice->buyer_name ?? $invoice->customer?->name ?? 'Detected Unknown' }}</div>
                        @if($invoice->buyer_state)
                            <span class="badge bg-light text-secondary border mt-1">{{ $invoice->buyer_state }}</span>
                        @endif
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <span class="text-muted">Total Amount:</span>
                            <div class="fw-bold fs-6 mono text-primary">₹{{ number_format($invoice->total_amount, 2) }}</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Line Items:</span>
                            <div class="fw-semibold text-dark">{{ $invoice->items->count() }} Extracted Items</div>
                        </div>
                    </div>

                    <!-- Shipping ID status -->
                    <div class="mb-2 p-2 rounded bg-light border d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size: 0.75rem;">Shipping ID / AWB:</span>
                        @if($resolvedShippingId)
                            <span class="fw-semibold mono text-success" style="font-size: 0.75rem;">
                                <i class="bi bi-truck me-1"></i> {{ $resolvedShippingId }}
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                                Unmatched Label
                            </span>
                        @endif
                    </div>

                    <!-- Validation Highlights Pills -->
                    @if($validation['has_issues'])
                        <div class="p-2 rounded bg-warning-subtle border border-warning-subtle mb-3">
                            <div class="fw-bold text-warning-emphasis mb-1" style="font-size: 0.72rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i> Audit Highlights:
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                @if(!empty($validation['missing_fields']))
                                    <span class="badge bg-danger text-white" style="font-size: 0.65rem;">
                                        Missing: {{ count($validation['missing_fields']) }} fields
                                    </span>
                                @endif
                                @if($validation['is_low_confidence'])
                                    <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">
                                        Low Conf ({{ number_format($validation['confidence_score'], 0) }}%)
                                    </span>
                                @endif
                                @if($validation['has_tax_discrepancy'])
                                    <span class="badge bg-danger text-white" style="font-size: 0.65rem;">
                                        Math Discrepancy
                                    </span>
                                @endif
                                @if($validation['is_possible_duplicate'])
                                    <span class="badge bg-danger text-white" style="font-size: 0.65rem;">
                                        Duplicate No.
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- AI Confidence Meter -->
                    <div class="p-2 rounded bg-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted" style="font-size: 0.72rem;">Extraction Confidence:</span>
                            <span class="fw-bold {{ ($invoice->ai_confidence_score ?? 90) >= 85 ? 'text-success' : 'text-warning' }}">{{ number_format($invoice->ai_confidence_score ?? 90, 0) }}%</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar {{ ($invoice->ai_confidence_score ?? 90) >= 85 ? 'bg-success' : 'bg-warning' }}" style="width: {{ $invoice->ai_confidence_score ?? 90 }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-light border px-2" title="Inspect Invoice Details">
                            <i class="bi bi-eye me-1"></i> Details
                        </a>
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-light border px-2" title="Edit Fields">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    </div>

                    <form action="{{ route('invoices.quickValidate', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-emerald px-3 rounded-pill shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Validate
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm py-5 text-center text-muted" style="border: 1px solid var(--slate-200) !important;">
                <i class="bi bi-clipboard-check fs-1 text-success d-block mb-2"></i>
                <h5>All Invoices Reviewed!</h5>
                <p class="small text-muted mb-0">No invoices currently awaiting audit. Check back after your next document upload.</p>
            </div>
        </div>
    @endforelse
</div>

@if($pendingReviews->hasPages())
    <div class="mt-4">
        {{ $pendingReviews->links() }}
    </div>
@endif
@endsection
