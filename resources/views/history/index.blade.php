@extends('layouts.app')

@section('page_title', 'Processing History & Audit Center')

@section('content')
<!-- Header Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Total Upload Batches</span>
                    <h4 class="fw-bold text-dark mb-0">{{ $totalBatches }}</h4>
                </div>
                <div class="rounded-3 p-2 bg-light text-success fs-4">
                    <i class="bi bi-folder2-open"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Processed Invoices</span>
                    <h4 class="fw-bold text-dark mb-0">{{ $totalInvoicesCount }}</h4>
                </div>
                <div class="rounded-3 p-2 bg-light text-primary fs-4">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Matched vs Unmatched</span>
                    <h5 class="fw-bold mb-0">
                        <span class="text-success">{{ $matchedCount }}</span> / <span class="text-warning-emphasis">{{ $unmatchedCount }}</span>
                    </h5>
                </div>
                <div class="rounded-3 p-2 bg-light text-info fs-4">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border: 1px solid var(--slate-200) !important;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Failed Documents</span>
                    <h4 class="fw-bold text-danger mb-0">{{ $failedDocsCount }}</h4>
                </div>
                <div class="rounded-3 p-2 bg-light text-danger fs-4">
                    <i class="bi bi-exclamation-octagon"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-pills mb-4 gap-2" id="historyTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'batches' ? 'active bg-emerald text-white' : 'btn-light border text-dark' }} rounded-pill px-3 py-2 fw-semibold" href="{{ route('history.index', ['tab' => 'batches']) }}">
            <i class="bi bi-collection me-1"></i> Upload Batches ({{ $batches->total() }})
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'invoices' ? 'active bg-emerald text-white' : 'btn-light border text-dark' }} rounded-pill px-3 py-2 fw-semibold" href="{{ route('history.index', ['tab' => 'invoices']) }}">
            <i class="bi bi-receipt me-1"></i> Invoice Processing History ({{ $invoices->total() }})
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'exports' ? 'active bg-emerald text-white' : 'btn-light border text-dark' }} rounded-pill px-3 py-2 fw-semibold" href="{{ route('history.index', ['tab' => 'exports']) }}">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel Export History ({{ $exports->total() }})
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'logs' ? 'active bg-emerald text-white' : 'btn-light border text-dark' }} rounded-pill px-3 py-2 fw-semibold" href="{{ route('history.index', ['tab' => 'logs']) }}">
            <i class="bi bi-clock-history me-1"></i> Operational Audit Trail ({{ $logs->total() }})
        </a>
    </li>
</ul>

@if($activeTab === 'batches')
    <!-- TAB 1: UPLOAD BATCHES -->
    <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-bold fs-6 text-dark">
                <i class="bi bi-folder2 text-success me-2"></i> Document Upload Batches ({{ $batches->total() }})
            </span>

            <form method="GET" action="{{ route('history.index') }}" class="d-flex gap-2">
                <input type="hidden" name="tab" value="batches">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="batch_search" class="form-control border-start-0" placeholder="Search batch name..." value="{{ request('batch_search') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-light border">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Batch Name</th>
                        <th>Upload Date</th>
                        <th>Processing Date</th>
                        <th class="text-center">Files & Pages</th>
                        <th class="text-center">Total Invoices</th>
                        <th class="text-center">Matched</th>
                        <th class="text-center">Unmatched</th>
                        <th class="text-center">Failed</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap" style="min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $b)
                        @php
                            $invoicesInBatch = $b->documents->whereNotNull('invoice_id')->count();
                            $matchedInBatch = $b->documents->filter(fn ($d) => $d->invoice && $d->invoice->matching_status === 'matched')->count();
                            $unmatchedInBatch = $b->documents->filter(fn ($d) => $d->invoice && $d->invoice->matching_status !== 'matched')->count();
                            $failedInBatch = $b->documents->where('extraction_status', \App\Models\Document::STATUS_FAILED)->count();
                        @endphp
                        <tr>
                            <td class="fw-semibold text-dark">
                                <a href="{{ route('batches.show', $b) }}" class="text-decoration-none" style="color: var(--emerald-700);">
                                    <i class="bi bi-folder-fill text-warning me-1"></i> {{ $b->batch_name }}
                                </a>
                                <div class="text-muted" style="font-size: 0.7rem;">Created by {{ $b->user?->name ?? 'User' }}</div>
                            </td>
                            <td class="text-muted text-nowrap">{{ $b->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-muted text-nowrap">{{ $b->updated_at->format('M d, Y H:i') }}</td>
                            <td class="text-center mono">
                                <span class="fw-semibold">{{ $b->total_files }}</span> files / {{ $b->total_pages }} pgs
                            </td>
                            <td class="text-center fw-bold mono text-dark">{{ $invoicesInBatch }}</td>
                            <td class="text-center mono text-success fw-semibold">{{ $matchedInBatch }}</td>
                            <td class="text-center mono text-warning-emphasis fw-semibold">{{ $unmatchedInBatch }}</td>
                            <td class="text-center mono text-danger fw-semibold">{{ $failedInBatch }}</td>
                            <td><x-badge-status :status="$b->status" /></td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('batches.show', $b) }}" class="btn btn-light border p-1 px-2" title="Inspect Batch">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <form action="{{ route('history.batches.reprocess', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Reprocess all documents in this batch?');">
                                        @csrf
                                        <button type="submit" class="btn btn-light border text-primary p-1 px-2" title="Reprocess Batch">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>

                                    <form action="{{ route('history.batches.destroy', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this batch and its documents?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-light border text-danger p-1 px-2" title="Delete Batch">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                                No document batches uploaded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($batches->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $batches->links() }}
            </div>
        @endif
    </div>

@elseif($activeTab === 'invoices')
    <!-- TAB 2: INVOICES PROCESSING HISTORY WITH SEARCH & FILTERS -->
    <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
        <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
            <i class="bi bi-funnel text-success me-2"></i> Search & Filter Processing History
        </div>
        <div class="card-body p-3 bg-light">
            <form method="GET" action="{{ route('history.index') }}" class="row g-2">
                <input type="hidden" name="tab" value="invoices">

                <div class="col-md-2">
                    <input type="text" name="invoice_number" class="form-control form-control-sm" placeholder="Invoice #" value="{{ request('invoice_number') }}">
                </div>
                <div class="col-md-2">
                    <input type="text" name="order_number" class="form-control form-control-sm mono" placeholder="Order #" value="{{ request('order_number') }}">
                </div>
                <div class="col-md-2">
                    <input type="text" name="po_number" class="form-control form-control-sm mono" placeholder="PO #" value="{{ request('po_number') }}">
                </div>
                <div class="col-md-2">
                    <input type="text" name="shipping_id" class="form-control form-control-sm mono" placeholder="Shipping ID / AWB" value="{{ request('shipping_id') }}">
                </div>
                <div class="col-md-2">
                    <input type="text" name="buyer_name" class="form-control form-control-sm" placeholder="Buyer Name" value="{{ request('buyer_name') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="invoice_date" class="form-control form-control-sm" value="{{ request('invoice_date') }}" title="Invoice Date">
                </div>
                <div class="col-md-2">
                    <select name="processing_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="extracted" {{ request('processing_status') == 'extracted' ? 'selected' : '' }}>Extracted</option>
                        <option value="validated" {{ request('processing_status') == 'validated' ? 'selected' : '' }}>Validated</option>
                        <option value="review_needed" {{ request('processing_status') == 'review_needed' ? 'selected' : '' }}>Review Needed</option>
                        <option value="failed" {{ request('processing_status') == 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-emerald w-100">
                        <i class="bi bi-funnel me-1"></i> Apply Filter
                    </button>
                    <a href="{{ route('history.index', ['tab' => 'invoices']) }}" class="btn btn-sm btn-light border" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Uploaded PDF Filename</th>
                        <th>Upload Date</th>
                        <th>Processing Date</th>
                        <th>Invoice #</th>
                        <th>Order / PO #</th>
                        <th>Shipping ID</th>
                        <th>Buyer Name</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap" style="min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                        @php
                            $resShipping = $inv->resolved_shipping_id;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 150px;" title="{{ $inv->document?->original_filename }}">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                    {{ $inv->document?->original_filename ?? 'Generated Record' }}
                                </div>
                            </td>
                            <td class="text-muted text-nowrap">{{ $inv->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-muted text-nowrap">{{ $inv->updated_at->format('M d, Y H:i') }}</td>
                            <td class="fw-bold">
                                <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none" style="color: var(--emerald-700);">
                                    {{ $inv->invoice_number ?? 'Draft' }}
                                </a>
                            </td>
                            <td class="mono">
                                <div>{{ $inv->order_number ?? '-' }}</div>
                                @if($inv->po_number)
                                    <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">PO: {{ $inv->po_number }}</span>
                                @endif
                            </td>
                            <td>
                                @if($resShipping)
                                    <span class="badge bg-emerald-subtle text-emerald-800 border border-emerald-subtle mono">
                                        {{ $resShipping }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">Unmatched</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-medium text-dark text-truncate" style="max-width: 130px;">
                                    {{ $inv->buyer_name ?? $inv->customer?->name ?? '-' }}
                                </div>
                            </td>
                            <td class="text-end fw-bold mono text-dark">
                                ₹{{ number_format($inv->total_amount, 2) }}
                            </td>
                            <td><x-badge-status :status="$inv->processing_status" /></td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('invoices.show', $inv) }}" class="btn btn-light border p-1 px-2" title="Inspect">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($inv->document)
                                        <form action="{{ route('history.documents.reprocess', $inv->document) }}" method="POST" class="d-inline" onsubmit="return confirm('Reprocess this document?');">
                                            @csrf
                                            <button type="submit" class="btn btn-light border text-primary p-1 px-2" title="Reprocess Document">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                                No invoice history found matching your filters.
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

@elseif($activeTab === 'exports')
    <!-- TAB 3: EXCEL EXPORT HISTORY & DOWNLOADS -->
    <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-6 text-dark">
                <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Generated Excel Exports & Downloads ({{ $exports->total() }})
            </span>
            <a href="{{ route('exports.index') }}" class="btn btn-sm btn-emerald rounded-pill px-3">
                <i class="bi bi-plus-lg me-1"></i> Generate New Export
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Export Name</th>
                        <th>Format</th>
                        <th>Records Count</th>
                        <th>Generated Date</th>
                        <th>User</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap" style="min-width: 160px; width: 170px;">Download Link</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exports as $exp)
                        <tr>
                            <td class="fw-semibold text-dark">
                                <i class="bi bi-file-earmark-excel text-success me-1"></i> {{ $exp->export_name }}
                            </td>
                            <td><span class="badge bg-success-subtle text-success text-uppercase">{{ $exp->format }}</span></td>
                            <td class="mono fw-semibold">{{ $exp->total_records }}</td>
                            <td class="text-muted text-nowrap">{{ $exp->created_at->format('M d, Y H:i:s') }}</td>
                            <td>{{ $exp->user?->name ?? 'System' }}</td>
                            <td><x-badge-status :status="$exp->status" /></td>
                            <td class="text-end text-nowrap">
                                @if($exp->status === 'completed')
                                    <a href="{{ route('exports.download', $exp) }}" class="btn-action-download">
                                        <i class="bi bi-cloud-arrow-down-fill"></i> Download XLSX
                                    </a>
                                @else
                                    <span class="badge bg-light text-muted border px-2.5 py-1">In Progress</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                                No Excel export files found in history.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($exports->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $exports->links() }}
            </div>
        @endif
    </div>

@elseif($activeTab === 'logs')
    <!-- TAB 4: AUDIT TRAIL LOGS -->
    <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-bold fs-6 text-dark">
                <i class="bi bi-shield-check text-success me-2"></i> Operational System Audit Trail ({{ $logs->total() }})
            </span>

            <form method="GET" action="{{ route('history.index') }}" class="d-flex gap-2">
                <input type="hidden" name="tab" value="logs">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="log_search" class="form-control border-start-0" placeholder="Filter audit trail..." value="{{ request('log_search') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-light border">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-muted mono">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.68rem;">
                                    {{ str_replace('_', ' ', $log->action) }}
                                </span>
                            </td>
                            <td class="fw-semibold text-dark">{{ $log->user?->name ?? 'System Service' }}</td>
                            <td>{{ $log->description }}</td>
                            <td class="text-muted mono">{{ $log->ip_address ?? '127.0.0.1' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-clock fs-1 text-secondary d-block mb-2"></i>
                                No activity audit records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endif
@endsection
