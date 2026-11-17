@extends('layouts.app')

@section('page_title', 'Extracted Raw Text: ' . $document->original_filename)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                @if($document->batch)
                    <li class="breadcrumb-item"><a href="{{ route('batches.show', $document->batch) }}" class="text-decoration-none text-muted">Batch #{{ $document->batch->id }}</a></li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('processing.index') }}" class="text-decoration-none text-muted">Queue</a></li>
                @endif
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Raw Text #{{ $document->id }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-code text-emerald-600" style="color: var(--emerald-600);"></i>
            {{ $document->original_filename }}
        </h4>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('documents.preview', $document) }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-eye me-1"></i> Preview PDF
        </a>
        <a href="{{ route('documents.download', $document) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-download me-1"></i> Download PDF
        </a>
        <form action="{{ route('documents.retry', $document) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-arrow-repeat me-1"></i> Re-Extract
            </button>
        </form>
    </div>
</div>

<!-- Metadata Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Total Pages</div>
            <h5 class="fw-bold mb-0 mt-1 text-dark">{{ $document->total_pages ?: $document->page_count ?: 1 }} Pages</h5>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Character Count</div>
            <h5 class="fw-bold mb-0 mt-1 text-dark">{{ number_format(mb_strlen($document->raw_text ?: '')) }} Chars</h5>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">Scanned Document</div>
            <div class="mt-1">
                @if($document->is_scanned)
                    <span class="badge bg-warning-subtle text-danger border border-danger-subtle px-2 py-1">Scanned PDF</span>
                @else
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Selectable Digital PDF</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small">OCR Engine</div>
            <div class="mt-1">
                @if($document->ocr_status === 'completed')
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">Applied: {{ ucfirst($document->ocr_provider ?: 'OCR') }}</span>
                @elseif($document->ocr_status === 'failed')
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">OCR Failed</span>
                @else
                    <span class="badge bg-light text-muted border px-2 py-1">Not Needed / None</span>
                @endif
            </div>
        </div>
    </div>
</div>

@if($document->error_message)
    <div class="alert alert-danger py-2 px-3 small mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
        <div><strong>Extraction Notice:</strong> {{ $document->error_message }}</div>
    </div>
@endif

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" id="textTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold text-dark" id="full-text-tab" data-bs-toggle="tab" data-bs-target="#full-text" type="button" role="tab" aria-controls="full-text" aria-selected="true">
            <i class="bi bi-file-text me-1"></i> Full Extracted Text
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold text-dark" id="page-breakdown-tab" data-bs-toggle="tab" data-bs-target="#page-breakdown" type="button" role="tab" aria-controls="page-breakdown" aria-selected="false">
            <i class="bi bi-layers me-1"></i> Page-wise Breakdown ({{ $document->pages->count() }})
        </button>
    </li>
</ul>

<div class="tab-content" id="textTabsContent">
    <!-- Full Aggregated Raw Text Tab -->
    <div class="tab-pane fade show active" id="full-text" role="tabpanel" aria-labelledby="full-text-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold small text-uppercase text-secondary">
                    <i class="bi bi-terminal me-1 text-emerald-600"></i> Raw Text Stream
                </span>
                <button type="button" class="btn btn-sm btn-light border" onclick="copyFullText()">
                    <i class="bi bi-clipboard me-1"></i> Copy Text
                </button>
            </div>
            <div class="card-body p-0">
                <pre class="m-0 p-4 bg-light border-0 text-dark mono small" id="rawTextContent" style="white-space: pre-wrap; word-break: break-word; max-height: 600px; overflow-y: auto;">{{ $document->raw_text ?: '(No text could be extracted from this document)' }}</pre>
            </div>
        </div>
    </div>

    <!-- Page-wise Breakdown Tab -->
    <div class="tab-pane fade" id="page-breakdown" role="tabpanel" aria-labelledby="page-breakdown-tab">
        <div class="d-flex flex-column gap-3">
            @forelse($document->pages as $page)
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-emerald-600 text-white rounded-pill px-3 py-1" style="background-color: var(--emerald-600) !important;">
                                Page {{ $page->page_number }}
                            </span>
                            <span class="text-muted small">({{ number_format($page->char_count) }} characters)</span>
                            @if($page->is_scanned)
                                <span class="badge bg-warning-subtle text-danger border border-danger-subtle">Scanned</span>
                            @endif
                            @if($page->ocr_applied)
                                <span class="badge bg-info-subtle text-info border border-info-subtle">OCR: {{ $page->ocr_provider }}</span>
                            @endif
                        </div>
                        <div>
                            @if($page->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Completed</span>
                            @elseif($page->status === 'empty')
                                <span class="badge bg-secondary-subtle text-muted border border-secondary-subtle">Empty</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                            @endif
                        </div>
                    </div>
                    @if($page->error_message)
                        <div class="p-2 px-3 bg-danger-subtle text-danger small border-bottom border-danger-subtle">
                            <i class="bi bi-exclamation-triangle me-1"></i> {{ $page->error_message }}
                        </div>
                    @endif
                    <div class="card-body p-0">
                        <pre class="m-0 p-3 bg-light border-0 text-dark mono small" style="white-space: pre-wrap; word-break: break-word; max-height: 350px; overflow-y: auto;">{{ $page->extracted_text ?: '(Empty page text)' }}</pre>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm p-4 text-center text-muted">
                    No granular page records found for this document.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyFullText() {
    const text = document.getElementById('rawTextContent').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Extracted raw text copied to clipboard!');
    }).catch(err => {
        console.error('Could not copy text: ', err);
    });
}
</script>
@endpush
