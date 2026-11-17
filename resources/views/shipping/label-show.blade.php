@extends('layouts.app')

@section('page_title', 'Shipping Label ' . $label->awb_number)

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('shipping.index') }}" class="text-decoration-none text-muted">Shipping</a></li>
                <li class="breadcrumb-item active" aria-current="page">Label #{{ $label->awb_number }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1 text-dark">
            Shipping Label: <span class="mono text-emerald">{{ $label->awb_number }}</span>
            @if($label->is_manually_corrected)
                <span class="badge bg-warning text-dark fs-6 ms-2">
                    <i class="bi bi-pencil-square me-1"></i> Manually Corrected
                </span>
            @endif
        </h4>
        <p class="text-muted small mb-0">Extracted from {{ $label->document?->original_filename ?? 'Direct Upload' }} (Page {{ $label->page_number }}).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('shipping.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Back to Shipping
        </a>
        <button type="button" class="btn btn-emerald btn-sm px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#editAwbModal">
            <i class="bi bi-pencil me-1"></i> Correct AWB Number
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4 mb-4">
    <!-- Key Details Card -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-card-checklist me-2 text-emerald"></i> Extracted Shipping Label Details</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Shipping ID / AWB Number</label>
                        <span class="fs-5 fw-bold mono text-dark">{{ $label->awb_number }}</span>
                        @if($label->original_awb_number && $label->original_awb_number !== $label->awb_number)
                            <div class="small text-muted mt-1">
                                <span class="badge bg-light text-secondary border">Original: {{ $label->original_awb_number }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Order Number</label>
                        <span class="fs-5 fw-semibold mono text-dark">{{ $label->order_number ?: 'N/A' }}</span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Courier Partner</label>
                        <span class="fw-semibold text-dark"><i class="bi bi-truck text-emerald me-1"></i> {{ $label->courier_name ?: 'Unknown' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Marketplace / Platform</label>
                        <span class="fw-semibold text-dark"><i class="bi bi-shop text-muted me-1"></i> {{ $label->marketplace_name ?: 'E-Commerce' }}</span>
                    </div>

                    <div class="col-12"><hr class="my-2 text-muted"></div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Buyer / Customer Name</label>
                        <span class="fw-bold text-dark fs-6">{{ $label->buyer_name ?: 'N/A' }}</span>
                    </div>
                    <div class="col-sm-3">
                        <label class="text-muted small d-block">State</label>
                        <span class="fw-semibold text-dark">{{ $label->state ?: 'N/A' }}</span>
                    </div>
                    <div class="col-sm-3">
                        <label class="text-muted small d-block">Pincode</label>
                        <span class="fw-bold mono text-dark">{{ $label->pincode ?: 'N/A' }}</span>
                    </div>

                    <div class="col-12">
                        <label class="text-muted small d-block">Complete Shipping Delivery Address</label>
                        <div class="p-3 bg-light rounded border text-secondary" style="white-space: pre-line;">
                            {{ $label->shipping_address ?: 'No address specified' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metadata & Audit Sidebar -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle me-2 text-emerald"></i> Extraction Metadata</h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Detected Format:</span>
                        <span class="badge bg-light text-dark border text-uppercase">{{ str_replace('_', ' ', $label->label_format) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Extraction Method:</span>
                        <span class="badge bg-emerald-subtle text-emerald border">{{ strtoupper($label->extraction_method) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Confidence Score:</span>
                        <span class="fw-bold text-dark">{{ $label->confidence_score }}%</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Page Reference:</span>
                        <span class="text-dark">Page {{ $label->page_number }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Linked Invoice:</span>
                        @if($label->invoice)
                            <a href="{{ route('invoices.show', $label->invoice) }}" class="fw-semibold text-emerald text-decoration-none">
                                {{ $label->invoice->invoice_number }}
                            </a>
                        @else
                            <span class="text-muted">Unlinked</span>
                        @endif
                    </li>
                </ul>
            </div>
        </div>

        @if($label->is_manually_corrected)
            <div class="card shadow-sm border-0 border-start border-warning border-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-body p-3 small">
                    <h6 class="fw-bold text-warning mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Audit Correction Trail</h6>
                    <p class="text-muted mb-1">Original AWB: <strong class="mono">{{ $label->original_awb_number }}</strong></p>
                    @if($label->correction_notes)
                        <p class="text-muted mb-0">Reason: <em>{{ $label->correction_notes }}</em></p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Raw OCR / Parsed Text Preview -->
@if(!empty($label->raw_text))
<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-text me-2 text-muted"></i> Source Page Raw Extracted Text</h6>
        <span class="badge bg-light text-muted border">{{ strlen($label->raw_text) }} characters</span>
    </div>
    <div class="card-body p-3">
        <pre class="bg-light p-3 rounded border small mono mb-0" style="max-height: 250px; overflow-y: auto;">{{ $label->raw_text }}</pre>
    </div>
</div>
@endif

<!-- Modal: Correct AWB -->
<div class="modal fade" id="editAwbModal" tabindex="-1" aria-labelledby="editAwbModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('shipping.labels.correctAwb', $label) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="editAwbModalLabel">Correct Shipping ID / AWB Number</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        If the automated OCR or layout parser misread a character (e.g. due to barcode glare or print fold), you can manually input the verified tracking identifier. The original extracted tracking number is preserved in audit records.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Current AWB Number</label>
                        <input type="text" class="form-control mono bg-light" value="{{ $label->awb_number }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="new_awb_input" class="form-label small fw-semibold">Verified New AWB / Tracking Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control mono" id="new_awb_input" name="awb_number" value="{{ old('awb_number', $label->awb_number) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="correction_notes_input" class="form-label small fw-semibold">Correction Reason / Notes</label>
                        <textarea class="form-control small" id="correction_notes_input" name="notes" rows="2" placeholder="e.g. Verified with courier portal or physical label barcode"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-emerald btn-sm rounded-pill px-4 shadow-sm">Save Correction</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
