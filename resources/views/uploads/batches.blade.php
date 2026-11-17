@extends('layouts.app')

@section('page_title', 'Upload Batches & History')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Document Upload Batches</h4>
        <p class="text-muted small mb-0">Track multi-file PDF ingestion batches, page totals, and processing status.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('uploads.invoices') }}" class="btn btn-emerald btn-sm rounded-pill px-3">
            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Invoices
        </a>
        <a href="{{ route('uploads.shipping') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-truck me-1"></i> Upload Shipping Labels
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-3" style="background-color: var(--emerald-50); color: var(--emerald-600);">
                    <i class="bi bi-folder2-open fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Batches</div>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($stats['total_batches']) }}</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-3 bg-light text-primary">
                    <i class="bi bi-files fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Files Ingested</div>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($stats['total_files']) }}</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-3 bg-light text-info">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total PDF Pages</div>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($stats['total_pages']) }}</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-3 bg-light {{ $stats['has_errors'] > 0 ? 'text-danger' : 'text-success' }}">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Batches with Errors</div>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($stats['has_errors']) }}</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Batches Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold small text-uppercase text-secondary">
            <i class="bi bi-clock-history me-1 text-success"></i> Batch Upload History
        </span>
        <form method="GET" action="{{ route('batches.index') }}" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="has_errors" {{ request('status') === 'has_errors' ? 'selected' : '' }}>Has Errors</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search batch name..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-sm btn-light border">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Batch ID</th>
                    <th>Batch Name</th>
                    @if(auth()->user()->isAdmin())
                        <th>User</th>
                    @endif
                    <th>Files Ingested</th>
                    <th>Total Pages</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td class="mono fw-semibold text-muted">#{{ $batch->id }}</td>
                        <td>
                            <a href="{{ route('batches.show', $batch) }}" class="fw-bold text-dark text-decoration-none">
                                <i class="bi bi-folder2 text-emerald-600 me-1" style="color: var(--emerald-600);"></i>
                                {{ $batch->batch_name }}
                            </a>
                        </td>
                        @if(auth()->user()->isAdmin())
                            <td>
                                <span class="badge bg-light text-dark border">{{ $batch->user?->name ?? 'System' }}</span>
                            </td>
                        @endif
                        <td>
                            <span class="badge bg-light text-dark border px-2 py-1">
                                <i class="bi bi-file-earmark-pdf text-danger me-1"></i> {{ $batch->total_files }} PDFs
                            </span>
                        </td>
                        <td class="fw-semibold">{{ $batch->total_pages }} pages</td>
                        <td>
                            @if($batch->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Completed</span>
                            @elseif($batch->status === 'processing' || $batch->status === 'pending')
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Processing</span>
                            @elseif($batch->status === 'has_errors')
                                <span class="badge bg-warning-subtle text-danger border border-danger-subtle px-2 py-1">Partial Errors</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Failed</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $batch->created_at->format('M d, Y H:i') }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('batches.show', $batch) }}" class="btn btn-sm btn-light border" title="View Batch Documents">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                @if(in_array($batch->status, ['failed', 'has_errors']))
                                    <form action="{{ route('batches.retry', $batch) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light border text-warning" title="Retry Failed Files">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('batches.destroy', $batch) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this batch and all associated documents permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete Batch">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                            No document upload batches found. Upload your first batch of invoices or shipping labels.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($batches->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $batches->links() }}
        </div>
    @endif
</div>
@endsection
