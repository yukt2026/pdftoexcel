@extends('layouts.app')

@section('page_title', 'Matched Invoices')

@section('content')
<!-- Header Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Matched Invoices</span>
                <div class="fs-4 fw-bold text-success">{{ $totalMatched }}</div>
                <div class="text-muted small" style="font-size: 0.75rem;">Successfully reconciled</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Reconciled Value</span>
                <div class="fs-4 fw-bold text-dark mono">₹{{ number_format($matchedValue, 2) }}</div>
                <div class="text-muted small" style="font-size: 0.75rem;">Zero discrepancy approved</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success p-2 rounded-circle">
                <i class="bi bi-patch-check-fill fs-6"></i>
            </span>
            <span class="fw-bold fs-6 text-dark">Verified & Reconciled Invoices</span>
        </div>

        <form method="GET" action="{{ route('invoices.matched') }}" class="d-flex gap-2">
            <div class="input-group input-group-sm" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search invoice or vendor..." value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn-sm btn-light border">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Invoice #</th>
                    <th>Vendor / Supplier</th>
                    <th>PO Ref</th>
                    <th>Total Amount</th>
                    <th>Linked Shipping Tracking</th>
                    <th>Reconciliation Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="fw-semibold">
                            <a href="{{ route('invoices.show', $invoice) }}" class="text-decoration-none" style="color: var(--emerald-700);">
                                {{ $invoice->invoice_number }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-medium text-dark">{{ $invoice->vendor?->name ?? 'N/A' }}</div>
                        </td>
                        <td><span class="badge bg-light text-dark border">{{ $invoice->po_number ?? 'Verified' }}</span></td>
                        <td class="fw-bold mono">₹{{ number_format($invoice->total_amount, 2) }}</td>
                        <td>
                            @if($invoice->shippingDocuments->count() > 0)
                                @foreach($invoice->shippingDocuments as $sDoc)
                                    <span class="badge bg-light text-secondary border mono"><i class="bi bi-truck me-1"></i>{{ $sDoc->tracking_number }}</span>
                                @endforeach
                            @else
                                <span class="badge bg-light text-muted border">2-Way PO Match</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check2 me-1"></i> Reconciled
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-light border p-1 px-2" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('invoices.export', $invoice) }}" class="btn btn-sm btn-light border text-success p-1 px-2" title="Export Excel">
                                <i class="bi bi-file-earmark-excel"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-patch-check fs-1 text-secondary d-block mb-2"></i>
                            No matched invoices found. Run the reconciliation engine to verify pending invoices.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $invoices->links() }}
        </div>
    @endif
</div>
@endsection
