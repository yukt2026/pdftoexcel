@extends('layouts.app')

@section('page_title', 'Invoice & Shipping Label Matching')

@section('content')
<!-- Page Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Invoice & Shipping Label Matching</h4>
        <p class="text-muted small mb-0">Intelligent 5-tier reconciliation engine matching Invoices to Shipping Labels (AWB) by Order No, PO No, and Recipient Verification.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="{{ route('matching.run') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-play-fill me-1"></i> Run Intelligent Matching
            </button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Metric Widgets Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('matching.index', ['tab' => 'matched']) }}" class="card shadow-sm border-0 text-decoration-none h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Matched</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ number_format($matchedCount) }}</h3>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background-color: var(--emerald-50); color: var(--emerald-600);">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('matching.index', ['tab' => 'needs_review']) }}" class="card shadow-sm border-0 text-decoration-none h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Needs Review</span>
                        <h3 class="fw-bold text-warning mb-0 mt-1">{{ number_format($needsReviewCount) }}</h3>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning-subtle text-warning" style="width: 44px; height: 44px;">
                        <i class="bi bi-exclamation-diamond fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('matching.index', ['tab' => 'unmatched_invoices']) }}" class="card shadow-sm border-0 text-decoration-none h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Unmatched Invoices</span>
                        <h3 class="fw-bold text-secondary mb-0 mt-1">{{ number_format($unmatchedInvoicesCount) }}</h3>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-secondary border" style="width: 44px; height: 44px;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('matching.index', ['tab' => 'unmatched_labels']) }}" class="card shadow-sm border-0 text-decoration-none h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Unmatched Labels</span>
                        <h3 class="fw-bold text-primary mb-0 mt-1">{{ number_format($unmatchedLabelsCount) }}</h3>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width: 44px; height: 44px;">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Main Content Tabs -->
<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white border-bottom px-3 pt-3 pb-0">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'matched' ? 'active fw-bold text-emerald' : 'text-muted' }}" href="{{ route('matching.index', ['tab' => 'matched']) }}">
                    <i class="bi bi-link-45deg me-1"></i> Matched Invoices
                    <span class="badge bg-light text-dark border ms-1">{{ $matchedCount }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'needs_review' ? 'active fw-bold text-warning' : 'text-muted' }}" href="{{ route('matching.index', ['tab' => 'needs_review']) }}">
                    <i class="bi bi-exclamation-triangle me-1"></i> Needs Review
                    @if($needsReviewCount > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $needsReviewCount }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'unmatched_invoices' ? 'active fw-bold text-dark' : 'text-muted' }}" href="{{ route('matching.index', ['tab' => 'unmatched_invoices']) }}">
                    <i class="bi bi-file-earmark-x me-1"></i> Unmatched Invoices
                    <span class="badge bg-light text-dark border ms-1">{{ $unmatchedInvoicesCount }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'unmatched_labels' ? 'active fw-bold text-dark' : 'text-muted' }}" href="{{ route('matching.index', ['tab' => 'unmatched_labels']) }}">
                    <i class="bi bi-tag me-1"></i> Unmatched Shipping Labels
                    <span class="badge bg-light text-dark border ms-1">{{ $unmatchedLabelsCount }}</span>
                </a>
            </li>
            @if($duplicateCount > 0)
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'duplicates' ? 'active fw-bold text-danger' : 'text-muted' }}" href="{{ route('matching.index', ['tab' => 'duplicates']) }}">
                    <i class="bi bi-files me-1"></i> Duplicate Flags
                    <span class="badge bg-danger text-white ms-1">{{ $duplicateCount }}</span>
                </a>
            </li>
            @endif
        </ul>
    </div>

    <!-- Tab 1: Matched Invoices -->
    @if($activeTab === 'matched')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Invoice #</th>
                    <th>Order Number</th>
                    <th>Shipping ID (AWB)</th>
                    <th>Buyer / Recipient</th>
                    <th>Courier</th>
                    <th>Method</th>
                    <th>Confidence</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matchedMatches as $m)
                    <tr>
                        <td class="fw-bold">
                            @if($m->invoice)
                                <a href="{{ route('invoices.show', $m->invoice) }}" class="text-emerald text-decoration-none">
                                    {{ $m->invoice->invoice_number }}
                                </a>
                            @else
                                <span class="mono">{{ $m->invoice_number }}</span>
                            @endif
                        </td>
                        <td class="mono fw-semibold text-dark">{{ $m->order_number ?: 'N/A' }}</td>
                        <td>
                            @if($m->shippingLabel)
                                <a href="{{ route('shipping.labels.show', $m->shippingLabel) }}" class="mono fw-bold text-dark text-decoration-none">
                                    {{ $m->awb_number }}
                                </a>
                            @else
                                <span class="mono fw-bold">{{ $m->awb_number }}</span>
                            @endif
                            @if($m->is_manually_matched)
                                <span class="badge bg-info-subtle text-info border ms-1" style="font-size: 0.65rem;">Manual</span>
                            @endif
                        </td>
                        <td>{{ $m->invoice?->buyer_name ?: $m->shippingLabel?->buyer_name ?: 'N/A' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-truck text-emerald me-1"></i>{{ $m->shippingLabel?->courier_name ?: 'Courier' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-success-subtle text-success border">
                                {{ $m->method_label }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div class="progress" style="width: 45px; height: 5px;">
                                    <div class="progress-bar bg-emerald" style="width: {{ $m->confidence_score }}%"></div>
                                </div>
                                <span class="mono" style="font-size: 0.72rem;">{{ round($m->confidence_score) }}%</span>
                            </div>
                        </td>
                        <td class="text-end">
                            <form action="{{ route('matching.unmatch', $m) }}" method="POST" class="d-inline" onsubmit="return confirm('Unlink this invoice from its shipping label?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light border text-danger p-1 px-2" title="Unlink Match">
                                    <i class="bi bi-link-45deg"></i> Unmatch
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-link-45deg fs-1 text-secondary d-block mb-2"></i>
                            No matched invoices yet. Click <strong>Run Intelligent Matching</strong> above to reconcile uploaded documents.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($matchedMatches->hasPages())
        <div class="card-footer bg-white py-3">{{ $matchedMatches->links() }}</div>
    @endif
    @endif

    <!-- Tab 2: Needs Review -->
    @if($activeTab === 'needs_review')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Invoice #</th>
                    <th>Order #</th>
                    <th>AWB Number</th>
                    <th>Invoice Buyer</th>
                    <th>Shipping Recipient</th>
                    <th>Review Reason / Discrepancy</th>
                    <th>Confidence</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($needsReviewMatches as $m)
                    <tr>
                        <td class="fw-bold">
                            @if($m->invoice)
                                <a href="{{ route('invoices.show', $m->invoice) }}" class="text-dark text-decoration-none">
                                    {{ $m->invoice->invoice_number }}
                                </a>
                            @else
                                <span class="mono">{{ $m->invoice_number }}</span>
                            @endif
                        </td>
                        <td class="mono fw-semibold">{{ $m->order_number }}</td>
                        <td class="mono fw-bold">{{ $m->awb_number }}</td>
                        <td>{{ $m->invoice?->buyer_name ?: 'N/A' }}</td>
                        <td>{{ $m->shippingLabel?->buyer_name ?: 'N/A' }}</td>
                        <td>
                            @if(!empty($m->discrepancy_details))
                                <ul class="list-unstyled mb-0 text-danger small">
                                    @foreach($m->discrepancy_details as $disc)
                                        <li><i class="bi bi-exclamation-circle me-1"></i> {{ $disc }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="badge bg-warning-subtle text-warning">Secondary verification needed</span>
                            @endif
                        </td>
                        <td class="mono">{{ round($m->confidence_score) }}%</td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <form action="{{ route('matching.approve', $m) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-success p-1 px-2" title="Approve Match">
                                        <i class="bi bi-check-lg"></i> Approve
                                    </button>
                                </form>
                                <form action="{{ route('matching.unmatch', $m) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger p-1 px-2" title="Reject / Unmatch">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-shield-check fs-1 text-success d-block mb-2"></i>
                            All matched pairs verified cleanly. Zero records currently need review.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($needsReviewMatches->hasPages())
        <div class="card-footer bg-white py-3">{{ $needsReviewMatches->links() }}</div>
    @endif
    @endif

    <!-- Tab 3: Unmatched Invoices -->
    @if($activeTab === 'unmatched_invoices')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Invoice #</th>
                    <th>Order Number</th>
                    <th>PO Number</th>
                    <th>Buyer Name</th>
                    <th>State</th>
                    <th>Total Amount</th>
                    <th class="text-end">Manual Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($unmatchedInvoices as $inv)
                    <tr>
                        <td class="fw-bold">
                            <a href="{{ route('invoices.show', $inv) }}" class="text-dark text-decoration-none">
                                {{ $inv->invoice_number }}
                            </a>
                        </td>
                        <td class="mono">{{ $inv->order_number ?: 'N/A' }}</td>
                        <td class="mono">{{ $inv->po_number ?: 'N/A' }}</td>
                        <td class="fw-semibold">{{ $inv->buyer_name ?: 'N/A' }}</td>
                        <td>{{ $inv->buyer_state ?: 'N/A' }}</td>
                        <td class="mono fw-bold">₹{{ number_format($inv->total_amount, 2) }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-emerald rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manualMatchModal{{ $inv->id }}">
                                <i class="bi bi-link me-1"></i> Link Shipping Label
                            </button>

                            <!-- Modal: Manual Match -->
                            <div class="modal fade text-start" id="manualMatchModal{{ $inv->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('matching.manual') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="invoice_id" value="{{ $inv->id }}">
                                        <div class="modal-content">
                                            <div class="modal-header border-bottom">
                                                <h6 class="modal-title fw-bold">Manual Link: Invoice #{{ $inv->invoice_number }}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Select Available Shipping Label (AWB)</label>
                                                    <select name="shipping_label_id" class="form-select form-select-sm" required>
                                                        <option value="">-- Choose Shipping Label --</option>
                                                        @foreach($candidateLabels as $cand)
                                                            <option value="{{ $cand->id }}">
                                                                {{ $cand->awb_number }} - {{ $cand->courier_name }} (Order: {{ $cand->order_number ?: 'None' }}, Recipient: {{ $cand->buyer_name }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Audit Notes (Optional)</label>
                                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Reason for manual association">
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top">
                                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-emerald btn-sm rounded-pill px-3 shadow-sm">Save Match</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-check2-circle fs-1 text-success d-block mb-2"></i>
                            All invoices have been matched to shipping labels.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($unmatchedInvoices->hasPages())
        <div class="card-footer bg-white py-3">{{ $unmatchedInvoices->links() }}</div>
    @endif
    @endif

    <!-- Tab 4: Unmatched Shipping Labels -->
    @if($activeTab === 'unmatched_labels')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>AWB / Tracking #</th>
                    <th>Order Number</th>
                    <th>Courier</th>
                    <th>Buyer / Recipient</th>
                    <th>Destination State</th>
                    <th>Pincode</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($unmatchedLabels as $lbl)
                    <tr>
                        <td class="fw-bold mono">
                            <a href="{{ route('shipping.labels.show', $lbl) }}" class="text-dark text-decoration-none">
                                {{ $lbl->awb_number }}
                            </a>
                        </td>
                        <td class="mono">{{ $lbl->order_number ?: 'N/A' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $lbl->courier_name ?: 'Courier' }}</span></td>
                        <td class="fw-semibold">{{ $lbl->buyer_name ?: 'N/A' }}</td>
                        <td>{{ $lbl->state ?: 'N/A' }}</td>
                        <td class="mono">{{ $lbl->pincode ?: 'N/A' }}</td>
                        <td class="text-end">
                            <a href="{{ route('shipping.labels.show', $lbl) }}" class="btn btn-sm btn-light border p-1 px-2" title="Inspect Label">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam fs-1 text-secondary d-block mb-2"></i>
                            No unmatched shipping labels found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($unmatchedLabels->hasPages())
        <div class="card-footer bg-white py-3">{{ $unmatchedLabels->links() }}</div>
    @endif
    @endif

    <!-- Tab 5: Duplicates -->
    @if($activeTab === 'duplicates')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Invoice #</th>
                    <th>Order #</th>
                    <th>Conflicting AWB</th>
                    <th>Flag Details</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($duplicateMatches as $m)
                    <tr>
                        <td class="fw-bold">{{ $m->invoice_number }}</td>
                        <td class="mono">{{ $m->order_number }}</td>
                        <td class="mono fw-bold text-danger">{{ $m->awb_number }}</td>
                        <td>
                            @if(!empty($m->discrepancy_details))
                                <span class="text-danger small">{{ implode(', ', $m->discrepancy_details) }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger">Duplicate AWB assignment attempted</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <form action="{{ route('matching.unmatch', $m) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light border text-danger p-1 px-2">
                                    <i class="bi bi-trash"></i> Remove Flag
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                            Zero duplicate AWB assignments detected.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
