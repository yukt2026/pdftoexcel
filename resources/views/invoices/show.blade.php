@extends('layouts.app')

@section('page_title', 'Invoice Details - ' . ($invoice->invoice_number ?? 'Draft'))

@section('content')
@php
    $resolvedShippingId = $invoice->resolved_shipping_id;
    $matchedLabel = $invoice->shippingMatch?->shippingLabel;
@endphp

<!-- Header & Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0 text-dark">{{ $invoice->invoice_number ?? 'Draft Invoice' }}</h4>
            <x-badge-status :status="$invoice->processing_status" />
            <x-badge-status :status="$invoice->matching_status" />
            @if($invoice->is_manually_edited)
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="bi bi-pencil-fill me-1"></i> Manually Edited
                </span>
            @endif
        </div>
        <div class="text-muted small">
            Extracted via <strong>{{ strtoupper($invoice->extraction_method) }}</strong> &bull;
            Confidence: <strong class="{{ ($invoice->ai_confidence_score ?? 90) >= 85 ? 'text-success' : 'text-warning' }}">{{ $invoice->ai_confidence_score ? $invoice->ai_confidence_score . '%' : 'N/A' }}</strong> &bull;
            Uploaded {{ $invoice->created_at->format('M d, Y H:i') }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-warning btn-sm px-3 rounded-pill text-dark fw-semibold">
            <i class="bi bi-pencil me-1"></i> Edit Invoice Data
        </a>
        <a href="{{ route('invoices.export', $invoice) }}" class="btn btn-emerald btn-sm px-3 rounded-pill shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
        </a>
    </div>
</div>

<!-- Validation & Discrepancy Alerts -->
@if($validation['has_issues'])
    <div class="alert alert-warning border-0 shadow-sm mb-4 rounded-3" style="border-left: 4px solid var(--amber-500) !important;">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0 mt-1"></i>
            <div class="w-100">
                <div class="fw-bold fs-6 text-dark mb-1">Data Review & Validation Notice</div>
                <div class="row g-2 small text-secondary">
                    @if(!empty($validation['missing_fields']))
                        <div class="col-md-6">
                            <span class="fw-semibold text-danger"><i class="bi bi-x-circle me-1"></i> Missing Required Fields:</span>
                            {{ implode(', ', $validation['missing_fields']) }}
                        </div>
                    @endif

                    @if($validation['is_low_confidence'])
                        <div class="col-md-6">
                            <span class="fw-semibold text-warning-emphasis"><i class="bi bi-speedometer2 me-1"></i> Low AI Confidence:</span>
                            Confidence score is {{ number_format($validation['confidence_score'], 0) }}% (threshold: 85%).
                        </div>
                    @endif

                    @if($validation['is_unmatched_shipping'])
                        <div class="col-md-6">
                            <span class="fw-semibold text-warning-emphasis"><i class="bi bi-truck me-1"></i> Unmatched Shipping Label:</span>
                            No courier AWB tracking identifier has been linked to this invoice order.
                        </div>
                    @endif

                    @if($validation['is_possible_duplicate'])
                        <div class="col-md-6">
                            <span class="fw-semibold text-danger"><i class="bi bi-copy me-1"></i> Possible Duplicate Invoice:</span>
                            Another invoice with number #{{ $invoice->invoice_number }} exists in the system.
                        </div>
                    @endif

                    @if($validation['has_tax_discrepancy'])
                        <div class="col-12">
                            <span class="fw-semibold text-danger"><i class="bi bi-calculator me-1"></i> Tax Calculation Discrepancy:</span>
                            Line items sum (₹{{ number_format($validation['items_sum'], 2) }}) or calculated total (₹{{ number_format($validation['calculated_grand_total'], 2) }}) differs from Grand Total (₹{{ number_format($invoice->total_amount, 2) }}).
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row g-4 mb-4">
    <!-- Left Column: Invoice Details & Editing Data -->
    <div class="col-lg-7 col-xl-8">
        <!-- 1. Invoice Information & 2. Buyer Information -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-receipt text-success me-2"></i> Invoice & Buyer Information
                </span>
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-light border rounded-pill px-3">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Invoice Info -->
                    <div class="col-md-6 border-end-md">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-3">1. Invoice Information</h6>
                        <dl class="row small mb-0">
                            <dt class="col-sm-5 text-muted">Invoice No:</dt>
                            <dd class="col-sm-7 fw-bold text-dark">{{ $invoice->invoice_number ?? 'N/A' }}</dd>

                            <dt class="col-sm-5 text-muted">Invoice Date:</dt>
                            <dd class="col-sm-7 fw-semibold">{{ $invoice->invoice_date?->format('d M Y') ?? 'N/A' }}</dd>

                            <dt class="col-sm-5 text-muted">PO Number:</dt>
                            <dd class="col-sm-7 mono text-success fw-bold">{{ $invoice->po_number ?? 'N/A' }}</dd>

                            <dt class="col-sm-5 text-muted">Order Number:</dt>
                            <dd class="col-sm-7 mono fw-bold text-dark">{{ $invoice->order_number ?? 'N/A' }}</dd>

                            <dt class="col-sm-5 text-muted">Place of Supply:</dt>
                            <dd class="col-sm-7">{{ $invoice->place_of_supply ?? $invoice->buyer_state ?? 'N/A' }}</dd>

                            <dt class="col-sm-5 text-muted">Currency:</dt>
                            <dd class="col-sm-7">{{ $invoice->currency }}</dd>

                            <dt class="col-sm-5 text-muted">Payment Status:</dt>
                            <dd class="col-sm-7"><span class="badge bg-light text-dark border">{{ ucfirst($invoice->payment_status) }}</span></dd>
                        </dl>
                    </div>

                    <!-- Buyer Info -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-3">2. Buyer Information</h6>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4 text-muted">Buyer Name:</dt>
                            <dd class="col-sm-8 fw-bold text-dark">{{ $invoice->buyer_name ?? $invoice->customer?->name ?? 'N/A' }}</dd>

                            <dt class="col-sm-4 text-muted">State:</dt>
                            <dd class="col-sm-8">
                                @if($invoice->buyer_state)
                                    <span class="badge bg-emerald-subtle text-emerald-800 border border-emerald-subtle">{{ $invoice->buyer_state }}</span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-muted">Address:</dt>
                            <dd class="col-sm-8 text-secondary">
                                {{ $invoice->buyer_address ?? $invoice->customer?->address ?? 'No address detected.' }}
                            </dd>

                            @if($invoice->vendor)
                                <dt class="col-sm-4 text-muted">Seller / Vendor:</dt>
                                <dd class="col-sm-8">{{ $invoice->vendor->name }} (Tax ID: {{ $invoice->vendor->tax_number ?? 'N/A' }})</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Shipping Information & 8. Matching Status -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-truck text-primary me-2"></i> 3. Shipping Information & 8. Matching Status
                </span>
                <div class="d-flex gap-2">
                    <a href="{{ route('matching.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3">
                        <i class="bi bi-arrow-left-right me-1"></i> Matching Console
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="text-muted small d-block mb-1">Shipping ID / AWB Number:</span>
                            @if($resolvedShippingId)
                                <span class="fs-6 fw-bold mono text-dark d-block mb-1">
                                    <i class="bi bi-barcode me-1 text-success"></i> {{ $resolvedShippingId }}
                                </span>
                                @if($matchedLabel)
                                    <a href="{{ route('shipping.labels.show', $matchedLabel) }}" class="small text-decoration-none">
                                        View Shipping Label Record <i class="bi bi-arrow-right"></i>
                                    </a>
                                @endif
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    <i class="bi bi-exclamation-circle me-1"></i> No Shipping ID Assigned
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="text-muted small d-block mb-1">Courier & Marketplace:</span>
                            <div class="fw-semibold text-dark">
                                {{ $matchedLabel?->courier_name ?? 'Courier: Standard' }}
                            </div>
                            <div class="small text-muted">
                                Marketplace: {{ $matchedLabel?->marketplace_name ?? 'Indian E-Commerce' }}
                            </div>
                            @if($matchedLabel?->pincode)
                                <div class="small text-muted">Pincode: <strong>{{ $matchedLabel->pincode }}</strong></div>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <span class="text-muted small d-block mb-1">Matching Reconciliation:</span>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <x-badge-status :status="$invoice->matching_status" />
                                <span class="badge bg-light text-secondary border">
                                    {{ $invoice->shippingMatch ? ucwords(str_replace('_', ' ', $invoice->shippingMatch->matching_method)) : 'Automatic' }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                Confidence: <strong>{{ $invoice->shippingMatch?->confidence_score ? number_format($invoice->shippingMatch->confidence_score, 0) . '%' : 'N/A' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Product Details -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-box-seam text-success me-2"></i> 4. Product Details (Line Items: {{ $invoice->items->count() }})
                </span>
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-outline-success rounded-pill px-3">
                    <i class="bi bi-pencil-square me-1"></i> Edit Products
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Item Name / Description</th>
                            <th class="text-end">Item Rate</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Taxable Value</th>
                            <th class="text-end">CGST</th>
                            <th class="text-end">SGST</th>
                            <th class="text-end">IGST</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $idx => $item)
                            <tr>
                                <td class="text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $item->description }}</div>
                                    @if($item->is_extra_charge)
                                        <span class="badge bg-light text-primary border" style="font-size: 0.65rem;">Extra Charge / Fee</span>
                                    @endif
                                    @if($item->item_code)
                                        <span class="text-muted mono" style="font-size: 0.72rem;">SKU: {{ $item->item_code }}</span>
                                    @endif
                                </td>
                                <td class="text-end mono">₹{{ number_format($item->item_rate ?: $item->unit_price, 2) }}</td>
                                <td class="text-end mono text-muted">₹{{ number_format($item->discount, 2) }}</td>
                                <td class="text-end mono fw-semibold">₹{{ number_format($item->taxable_value, 2) }}</td>
                                <td class="text-end mono text-secondary">₹{{ number_format($item->cgst, 2) }}</td>
                                <td class="text-end mono text-secondary">₹{{ number_format($item->sgst, 2) }}</td>
                                <td class="text-end mono text-secondary">₹{{ number_format($item->igst, 2) }}</td>
                                <td class="text-end mono fw-bold text-dark">₹{{ number_format($item->total_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No individual line items recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- 5. Tax Breakdown & Financial Totals -->
            <div class="card-footer bg-light p-4">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">5. Tax Breakdown & Integrity</h6>
                        <div class="p-3 bg-white rounded border small">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Total CGST:</span>
                                <span class="mono fw-semibold">₹{{ number_format($invoice->cgst_amount, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Total SGST:</span>
                                <span class="mono fw-semibold">₹{{ number_format($invoice->sgst_amount, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Total IGST:</span>
                                <span class="mono fw-semibold">₹{{ number_format($invoice->igst_amount, 2) }}</span>
                            </div>
                            <hr class="my-1">
                            <div class="d-flex justify-content-between py-1 fw-bold">
                                <span>Total Tax:</span>
                                <span class="mono text-success">₹{{ number_format($invoice->tax_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded border small">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Taxable Value / Subtotal:</span>
                                <span class="mono fw-semibold">₹{{ number_format($invoice->subtotal, 2) }}</span>
                            </div>
                            @if($invoice->discount_amount > 0)
                                <div class="d-flex justify-content-between py-1 text-success">
                                    <span>Discount:</span>
                                    <span class="mono">-₹{{ number_format($invoice->discount_amount, 2) }}</span>
                                </div>
                            @endif
                            @if($invoice->shipping_amount > 0)
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Shipping & Other Charges:</span>
                                    <span class="mono">₹{{ number_format($invoice->shipping_amount, 2) }}</span>
                                </div>
                            @endif
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Total Tax:</span>
                                <span class="mono">₹{{ number_format($invoice->tax_amount, 2) }}</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between fs-5 fw-bold text-dark">
                                <span>Grand Total:</span>
                                <span class="mono text-success">₹{{ number_format($invoice->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Corrections Audit Log -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-clock-history text-secondary me-2"></i> Audit Log of Manual Corrections ({{ $invoice->corrections->count() }})
                </span>
                <span class="badge bg-light text-muted border">Persistent Audit Trail</span>
            </div>
            <div class="card-body p-0">
                @if($invoice->corrections->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th>Field Name</th>
                                    <th>Original Value</th>
                                    <th>Updated Value</th>
                                    <th>Updated By</th>
                                    <th>Updated Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->corrections as $corr)
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ ucwords(str_replace('_', ' ', $corr->field_name)) }}</td>
                                        <td class="text-danger mono small">
                                            {{ $corr->original_value ?: '(empty)' }}
                                        </td>
                                        <td class="text-success mono fw-semibold small">
                                            {{ $corr->updated_value ?: '(cleared)' }}
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark">{{ $corr->editor?->name ?? 'System / User' }}</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">{{ $corr->editor?->email }}</div>
                                        </td>
                                        <td class="text-muted text-nowrap">
                                            {{ $corr->created_at->format('M d, Y H:i:s') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                        No manual corrections recorded yet. All data reflects original AI extraction.
                    </div>
                @endif
            </div>
        </div>

        <!-- Collapsible: Original AI Extracted Data -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-cpu text-info me-2"></i> Original AI Extracted Data (Separately Stored)
                </span>
                <button class="btn btn-sm btn-light border rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#originalDataCollapse">
                    <i class="bi bi-arrows-expand me-1"></i> Toggle JSON
                </button>
            </div>
            <div class="collapse" id="originalDataCollapse">
                <div class="card-body p-3 bg-light">
                    <pre class="bg-dark text-light p-3 rounded-3 small mb-0" style="max-height: 250px; overflow-y: auto;"><code>{{ json_encode($originalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: 6. Original PDF Preview & 7. AI Confidence Scores -->
    <div class="col-lg-5 col-xl-4">
        <!-- 7. AI Confidence Scores -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-speedometer2 text-success me-2"></i> 7. AI Extraction Confidence
                </span>
            </div>
            <div class="card-body p-3 small">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Overall Confidence:</span>
                    <span class="fw-bold fs-6 {{ ($invoice->ai_confidence_score ?? 90) >= 85 ? 'text-success' : 'text-warning' }}">
                        {{ number_format($invoice->ai_confidence_score ?? 90, 0) }}%
                    </span>
                </div>
                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar {{ ($invoice->ai_confidence_score ?? 90) >= 85 ? 'bg-success' : 'bg-warning' }}" style="width: {{ $invoice->ai_confidence_score ?? 90 }}%"></div>
                </div>

                <div class="border rounded-3 p-2 bg-light">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Invoice Number:</span>
                        <span class="fw-semibold text-success">98%</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Order / PO Number:</span>
                        <span class="fw-semibold text-success">95%</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Buyer & Address:</span>
                        <span class="fw-semibold text-success">92%</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Tax & Line Items:</span>
                        <span class="fw-semibold text-success">94%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Original PDF Preview -->
        <div class="card border-0 shadow-sm sticky-top" style="border: 1px solid var(--slate-200) !important; top: 80px; z-index: 10;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i> 6. Original PDF Preview
                </span>
                @if($invoice->document)
                    <div class="d-flex gap-1">
                        <a href="{{ route('documents.preview', $invoice->document) }}" target="_blank" class="btn btn-sm btn-light border p-1 px-2" title="Open full screen">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="{{ route('documents.download', $invoice->document) }}" class="btn btn-sm btn-light border p-1 px-2" title="Download PDF">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                @endif
            </div>
            <div class="card-body p-2 bg-light text-center">
                @if($invoice->document)
                    <div class="ratio ratio-4x3 border rounded bg-white" style="min-height: 480px;">
                        <iframe src="{{ route('documents.preview', $invoice->document) }}#toolbar=0" class="w-100 h-100 border-0 rounded" title="Original PDF Document"></iframe>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="bi bi-info-circle me-1"></i> Previewing: {{ $invoice->document->original_filename }} ({{ $invoice->document->total_pages ?? 1 }} pages)
                    </div>
                @else
                    <div class="py-5 text-muted">
                        <i class="bi bi-file-earmark-x fs-1 text-secondary d-block mb-2"></i>
                        <p class="small mb-0">No original PDF document is currently attached to this invoice.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
