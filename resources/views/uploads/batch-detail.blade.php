@extends('layouts.app')

@section('page_title', 'Batch: ' . $batch->batch_name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('batches.index') }}" class="text-decoration-none text-muted">Upload Batches</a></li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Batch #{{ $batch->id }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-folder-check text-emerald-600" style="color: var(--emerald-600);"></i>
            {{ $batch->batch_name }}
        </h4>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('exports.invoices') }}" class="btn btn-emerald btn-sm rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-excel me-1"></i> Download Excel (.XLSX)
        </a>
        <a href="{{ route('batches.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Batches
        </a>
        @if(in_array($batch->status, ['failed', 'has_errors']))
            <form action="{{ route('batches.retry', $batch) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3 fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Retry Failed Files
                </button>
            </form>
        @endif
        <form action="{{ route('batches.destroy', $batch) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this entire batch and all associated document files permanently?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="bi bi-trash me-1"></i> Delete Batch
            </button>
        </form>
    </div>
</div>

<!-- Batch Metadata Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Batch Status</div>
            <div class="mt-1">
                @if($batch->status === 'completed')
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-6">Completed</span>
                @elseif($batch->status === 'processing' || $batch->status === 'pending')
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 fs-6">Processing</span>
                @elseif($batch->status === 'has_errors')
                    <span class="badge bg-warning-subtle text-danger border border-danger-subtle px-2 py-1 fs-6">Partial Errors</span>
                @else
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-6">Failed</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Total Documents</div>
            <h5 class="fw-bold mb-0 mt-1 text-dark">{{ $batch->total_files }} Files</h5>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Total Extracted Pages</div>
            <h5 class="fw-bold mb-0 mt-1 text-dark">{{ $batch->total_pages }} Pages</h5>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Batch Created</div>
            <h6 class="fw-bold mb-0 mt-1 text-dark">{{ $batch->created_at->format('M d, Y H:i:s') }}</h6>
        </div>
    </div>
</div>

<!-- Documents in Batch Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold small text-uppercase text-secondary">
            <i class="bi bi-file-earmark-pdf me-1 text-danger"></i> Ingested Documents ({{ $documents->total() }})
        </span>
        <a href="{{ route('exports.invoices') }}" class="btn btn-sm btn-action-download">
            <i class="bi bi-file-earmark-excel"></i> Download Batch Excel (.XLSX)
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Document</th>
                    <th>Type</th>
                    <th>File Size</th>
                    <th>Pages</th>
                    <th>SHA-256 Hash</th>
                    <th>Status</th>
                    <th>Associated Record</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                                <div>
                                    <div class="fw-bold text-dark">{{ $doc->original_filename }}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Uploaded {{ $doc->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            @if($doc->error_message)
                                <div class="mt-1 text-danger small bg-danger-subtle p-1 px-2 rounded border border-danger-subtle" style="font-size: 0.75rem;">
                                    <i class="bi bi-exclamation-circle me-1"></i> {{ $doc->error_message }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($doc->isInvoice())
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Invoice</span>
                            @else
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Shipping Label</span>
                            @endif
                        </td>
                        <td class="mono">{{ $doc->formatted_size }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $doc->total_pages ?: $doc->page_count ?: 1 }} pgs</span>
                        </td>
                        <td class="mono text-muted" style="font-size: 0.75rem;" title="{{ $doc->file_hash ?: $doc->hash }}">
                            {{ substr($doc->file_hash ?: $doc->hash ?: 'N/A', 0, 12) }}...
                        </td>
                        <td>
                            @php
                                $status = $doc->extraction_status ?: $doc->status;
                            @endphp
                            @if($status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Extracted</span>
                            @elseif($status === 'processing' || $status === 'parsing')
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Processing</span>
                            @elseif($status === 'failed')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($doc->invoice)
                                <a href="{{ route('invoices.show', $doc->invoice) }}" class="fw-semibold text-decoration-none" style="color: var(--emerald-700);">
                                    <i class="bi bi-receipt me-1"></i> {{ $doc->invoice->invoice_number }}
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex gap-1 align-items-center">
                                @if($doc->invoice)
                                    <a href="{{ route('invoices.export', $doc->invoice) }}" class="btn btn-sm btn-action-download" title="Download Excel XLSX for this Document">
                                        <i class="bi bi-file-earmark-excel"></i> Download Excel
                                    </a>
                                @endif
                                <a href="{{ route('documents.preview', $doc) }}" target="_blank" class="btn btn-sm btn-light border" title="Preview PDF inline">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('documents.download', $doc) }}" class="btn btn-sm btn-light border text-primary" title="Download original PDF">
                                    <i class="bi bi-download"></i>
                                </a>
                                @if($doc->extraction_status === 'failed' || $doc->status === 'failed' || $doc->extraction_status === 'pending')
                                    <form action="{{ route('documents.retry', $doc) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light border text-warning" title="Retry Extraction">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this document and its file permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete Document">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No documents found in this batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($documents->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $documents->links() }}
        </div>
    @endif
</div>
@endsection
