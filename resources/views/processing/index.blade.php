@extends('layouts.app')

@section('page_title', 'Processing Queue')

@section('content')
<!-- Queue Overview Header -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Pending In Queue</span>
                    <div class="fs-4 fw-bold text-warning">{{ $pendingCount }}</div>
                </div>
                <div class="rounded-circle p-2 bg-warning-subtle text-warning">
                    <i class="bi bi-hourglass-split fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Completed Documents</span>
                    <div class="fs-4 fw-bold text-success">{{ $completedCount }}</div>
                </div>
                <div class="rounded-circle p-2 bg-success-subtle text-success">
                    <i class="bi bi-check-circle fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Failed or Flagged</span>
                    <div class="fs-4 fw-bold text-danger">{{ $failedCount }}</div>
                </div>
                <div class="rounded-circle p-2 bg-danger-subtle text-danger">
                    <i class="bi bi-exclamation-octagon fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold fs-6 text-dark"><i class="bi bi-cpu text-primary me-2"></i> Extraction & OCR Pipeline Queue</span>
        
        <div class="d-flex gap-2">
            <form action="{{ route('processing.batch') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-emerald rounded-pill px-3 shadow-sm">
                    <i class="bi bi-play-circle me-1"></i> Process All Pending
                </button>
            </form>
            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>Doc #</th>
                    <th>Document Name</th>
                    <th>Type</th>
                    <th>File Size</th>
                    <th class="text-center">Pages</th>
                    <th>Uploaded</th>
                    <th>Pipeline Status</th>
                    <th>Extracted Invoice</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                    <tr>
                        <td class="text-muted mono">#{{ $doc->id }}</td>
                        <td class="fw-semibold text-dark">
                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                            {{ $doc->original_filename }}
                        </td>
                        <td><span class="badge bg-light text-secondary border text-uppercase">{{ str_replace('_', ' ', $doc->file_type) }}</span></td>
                        <td>{{ number_format($doc->file_size / 1024, 1) }} KB</td>
                        <td class="text-center">{{ $doc->page_count ?? 1 }}</td>
                        <td class="text-muted">{{ $doc->created_at->format('M d, H:i') }}</td>
                        <td>
                            <x-badge-status :status="$doc->status" />
                            @if($doc->error_message)
                                <div class="text-danger small mt-1" style="font-size: 0.7rem;">{{ Str::limit($doc->error_message, 35) }}</div>
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
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('documents.preview', $doc) }}" target="_blank" class="btn btn-sm btn-light border p-1 px-2" title="Preview PDF">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('documents.rawText', $doc) }}" class="btn btn-sm btn-light border p-1 px-2 text-dark" title="View Extracted Raw Text">
                                    <i class="bi bi-file-earmark-code"></i>
                                </a>
                                <a href="{{ route('documents.download', $doc) }}" class="btn btn-sm btn-light border text-primary p-1 px-2" title="Download PDF">
                                    <i class="bi bi-download"></i>
                                </a>
                                @if($doc->status === 'uploaded' || $doc->status === 'failed' || $doc->extraction_status === 'failed')
                                    <form action="{{ route('documents.retry', $doc) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light border text-success p-1 px-2" title="Process File">
                                            <i class="bi bi-play-fill"></i> Run
                                        </button>
                                    </form>
                                @elseif($doc->invoice)
                                    <a href="{{ route('invoices.show', $doc->invoice) }}" class="btn btn-sm btn-light border p-1 px-2 text-success" title="View Extracted Data">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block text-secondary mb-2"></i>
                            No documents currently in queue.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($documents->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $documents->links() }}
        </div>
    @endif
</div>
@endsection
