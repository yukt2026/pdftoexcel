@extends('layouts.app')

@section('page_title', 'Unmatched Invoices')

@section('content')
<!-- Header Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Pending Reconciliation</span>
                <div class="fs-4 fw-bold text-warning">{{ $totalUnmatched }}</div>
                <div class="text-muted small" style="font-size: 0.75rem;">Requires PO or Waybill matching</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3">
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Unmatched Volume</span>
                <div class="fs-4 fw-bold text-dark mono">₹{{ number_format($unmatchedValue, 2) }}</div>
                <div class="text-muted small" style="font-size: 0.75rem;">Awaiting verification</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning-subtle text-warning p-2 rounded-circle">
                <i class="bi bi-exclamation-triangle-fill fs-6"></i>
            </span>
            <span class="fw-bold fs-6 text-dark">Unmatched / Variance Flagged Invoices</span>
        </div>

        <form method="GET" action="{{ route('invoices.unmatched') }}" class="d-flex gap-2">
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
                    <th>Vendor</th>
                    <th>PO Number</th>
                    <th>Total</th>
                    <th>Flag / Discrepancy</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="fw-semibold">
                            <a href="{{ route('invoices.show', $invoice) }}" class="text-decoration-none text-dark">
                                {{ $invoice->invoice_number }}
                            </a>
                        </td>
                        <td>{{ $invoice->vendor?->name ?? 'N/A' }}</td>
                        <td>
                            @if($invoice->po_number)
                                <span class="badge bg-light text-dark border">{{ $invoice->po_number }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger">Missing PO Ref</span>
                            @endif
                        </td>
                        <td class="fw-bold mono">₹{{ number_format($invoice->total_amount, 2) }}</td>
                        <td>
                            @if($invoice->matchingRecords->count() > 0)
                                @php $latestMatch = $invoice->matchingRecords->last(); @endphp
                                @if(!empty($latestMatch->discrepancy_details))
                                    <span class="badge bg-danger-subtle text-danger" title="{{ json_encode($latestMatch->discrepancy_details) }}">
                                        {{ count($latestMatch->discrepancy_details) }} variance issue(s)
                                    </span>
                                @else
                                    <span class="text-muted">Awaiting review</span>
                                @endif
                            @else
                                <span class="text-muted">Unprocessed</span>
                            @endif
                        </td>
                        <td><x-badge-status :status="$invoice->matching_status" /></td>
                        <td class="text-end">
                            <form action="{{ route('matching.run') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-success p-1 px-2" title="Run Matching Engine">
                                    <i class="bi bi-shield-check"></i> Reconcile
                                </button>
                            </form>
                            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-light border p-1 px-2" title="Edit Data">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-check2-all fs-1 text-success d-block mb-2"></i>
                            All invoices are currently matched and reconciled! Zero unmatched discrepancies.
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
