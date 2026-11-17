@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
<!-- Quick Operations Toolbar -->
<div class="card mb-4 border shadow-sm" style="border-color: var(--border-color) !important; border-left: 4px solid var(--primary-teal) !important;">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h6 class="fw-bold mb-1 d-flex align-items-center gap-2" style="color: var(--primary-navy);">
                    <i class="bi bi-lightning-charge-fill text-warning"></i> Quick Operations
                </h6>
                <p class="small mb-0" style="color: var(--text-secondary);">Upload new batches, trigger background processing, or download real-time Excel workbooks.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('uploads.invoices') }}" class="btn btn-sm btn-teal rounded-pill px-3 shadow-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Upload Invoice PDF
                </a>
                <a href="{{ route('uploads.shipping') }}" class="btn btn-sm btn-outline-teal rounded-pill px-3">
                    <i class="bi bi-truck me-1"></i> Upload Shipping Label PDF
                </a>
                <form action="{{ route('processing.batch') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light border rounded-pill px-3 text-dark">
                        <i class="bi bi-cpu me-1" style="color: var(--primary-teal);"></i> Process Documents
                    </button>
                </form>
                <a href="{{ route('exports.invoices') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-medium" style="color: var(--primary-teal);">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 8 Dashboard Metric Widgets Grid -->
<div class="row g-3 mb-4">
    <!-- 1. Total Invoices -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Total Invoices" 
            :value="$totalInvoices" 
            icon="bi-receipt" 
            iconBg="bg-primary-subtle text-primary"
            subtext="Invoices in database"
        />
    </div>

    <!-- 2. Total PDFs Uploaded -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Total PDFs Uploaded" 
            :value="$totalPdfsUploaded" 
            icon="bi-file-earmark-pdf" 
            iconBg="bg-danger-subtle text-danger"
            subtext="Source PDF documents"
        />
    </div>

    <!-- 3. Processed Invoices -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Processed Invoices" 
            :value="$processedInvoices" 
            icon="bi-check2-circle" 
            iconBg="badge-subtle-primary"
            subtext="Extracted & validated"
        />
    </div>

    <!-- 4. Pending Invoices -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Pending Invoices" 
            :value="$pendingInvoices" 
            icon="bi-hourglass-split" 
            iconBg="bg-warning-subtle text-warning"
            subtext="Awaiting pipeline run"
        />
    </div>

    <!-- 5. Failed Invoices -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Failed Invoices" 
            :value="$failedInvoices" 
            icon="bi-x-octagon" 
            iconBg="bg-danger-subtle text-danger"
            subtext="Parsing errors or flags"
        />
    </div>

    <!-- 6. Matched Shipping Labels -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Matched Shipping Labels" 
            :value="$matchedShippingLabels" 
            icon="bi-truck" 
            iconBg="bg-info-subtle text-info"
            subtext="Waybills & manifests verified"
        />
    </div>

    <!-- 7. Unmatched Invoices -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Unmatched Invoices" 
            :value="$unmatchedInvoices" 
            icon="bi-exclamation-triangle" 
            iconBg="bg-warning-subtle text-warning"
            subtext="Missing PO or variance"
        />
    </div>

    <!-- 8. Total Excel Exports -->
    <div class="col-xl-3 col-sm-6">
        <x-metric-card 
            title="Total Excel Exports" 
            :value="$totalExcelExports" 
            icon="bi-file-earmark-spreadsheet" 
            iconBg="bg-success-subtle text-success"
            subtext="Generated workbooks"
        />
    </div>
</div>

<div class="row g-4">
    <!-- Recent Upload Table -->
    <div class="col-lg-8">
        <div class="card h-100 border shadow-sm" style="border-color: var(--border-color) !important;">
            <div class="card-header d-flex align-items-center justify-content-between py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-up" style="color: var(--primary-teal);"></i>
                    <span class="fw-bold fs-6" style="color: var(--primary-navy);">Recent Uploads</span>
                    <span class="badge bg-light text-secondary border rounded-pill small" style="font-size: 0.7rem;">{{ $recentUploads->count() }} items</span>
                </div>
                <a href="{{ route('processing.index') }}" class="btn btn-sm btn-link text-decoration-none small fw-semibold" style="color: var(--primary-teal);">
                    View Queue <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Document Type</th>
                            <th>Upload Date</th>
                            <th class="text-center">Total Pages</th>
                            <th>Processing Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUploads as $upload)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 220px; color: var(--text-primary);" title="{{ $upload->original_filename }}">
                                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i> {{ $upload->original_filename }}
                                    </div>
                                    <span class="text-muted mono" style="font-size: 0.7rem;">{{ number_format($upload->file_size / 1024, 1) }} KB</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.03em;">
                                        {{ str_replace('_', ' ', $upload->file_type) }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ $upload->created_at->format('M d, Y H:i') }}</td>
                                <td class="text-center fw-semibold" style="color: var(--text-primary);">{{ $upload->page_count ?? 1 }}</td>
                                <td>
                                    <x-badge-status :status="$upload->status" />
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if($upload->invoice)
                                            <a href="{{ route('invoices.show', $upload->invoice) }}" class="btn btn-light border" title="View Extracted Invoice">
                                                <i class="bi bi-eye" style="color: var(--primary-teal);"></i>
                                            </a>
                                        @elseif($upload->status === 'uploaded' || $upload->status === 'failed')
                                            <form action="{{ route('processing.single', $upload) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-light border text-success" title="Process File Now">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted" style="white-space: normal;">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width: 44px; height: 44px; background-color: var(--teal-light); color: var(--primary-teal);">
                                        <i class="bi bi-inbox fs-4"></i>
                                    </div>
                                    <div class="fw-semibold text-dark">No recent document uploads found</div>
                                    <div class="small text-muted mt-1">Upload new PDF invoices or shipping labels to begin processing.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Financial Overview, Queue & Pipeline Guide -->
    <div class="col-lg-4">
        <!-- 1. Total Invoice Volume Card -->
        <div class="card mb-4 border shadow-sm" style="border-color: var(--border-color) !important;">
            <div class="card-header py-3 fw-bold" style="color: var(--primary-navy);">
                <i class="bi bi-currency-rupee me-1" style="color: var(--primary-teal);"></i> Total Invoice Volume
            </div>
            <div class="card-body">
                <div class="fw-bold mono" style="font-size: 1.85rem; color: var(--primary-navy); letter-spacing: -0.02em;">
                    ₹{{ number_format($totalSpend, 2) }}
                </div>
                <div class="small mt-1" style="color: var(--text-secondary);">Aggregated across all extracted invoices</div>
                
                <hr class="my-3" style="border-color: var(--border-color);">
                
                <div class="d-flex justify-content-between align-items-center small mb-2">
                    <span style="color: var(--text-secondary);">Average AI Confidence:</span>
                    <span class="fw-bold" style="color: var(--primary-teal);">{{ $totalInvoices > 0 ? number_format($avgConfidence, 1) . '%' : '0.0%' }}</span>
                </div>
                <div class="progress" style="height: 6px; background-color: #F1F5F9;">
                    <div class="progress-bar" role="progressbar" style="width: {{ $totalInvoices > 0 ? min(100, max(0, $avgConfidence)) : 0 }}%; background-color: var(--primary-teal);" aria-valuenow="{{ $avgConfidence }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="row g-2 mt-3 pt-2 border-top" style="border-color: var(--border-color) !important;">
                    <div class="col-4 text-center">
                        <div class="small text-muted" style="font-size: 0.7rem;">Processed</div>
                        <div class="fw-bold small text-success">{{ $processedInvoices }}</div>
                    </div>
                    <div class="col-4 text-center border-start border-end" style="border-color: var(--border-color) !important;">
                        <div class="small text-muted" style="font-size: 0.7rem;">Pending</div>
                        <div class="fw-bold small text-warning">{{ $pendingInvoices }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="small text-muted" style="font-size: 0.7rem;">Failed</div>
                        <div class="fw-bold small text-danger">{{ $failedInvoices }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Processing Queue Status Card -->
        <div class="card mb-4 border shadow-sm" style="border-color: var(--border-color) !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2 small text-uppercase" style="letter-spacing: 0.05em; color: var(--primary-navy);">
                        <i class="bi bi-cpu" style="color: var(--primary-teal);"></i> Processing Queue
                    </h6>
                    @if($pendingInvoices > 0)
                        <span class="badge bg-warning text-dark rounded-pill small" style="font-size: 0.65rem;">Active</span>
                    @else
                        <span class="badge bg-success-subtle text-success rounded-pill small" style="font-size: 0.65rem;">Idle</span>
                    @endif
                </div>
                <p class="small mb-3" style="color: var(--text-secondary); font-size: 0.78rem;">
                    {{ $pendingInvoices }} job(s) pending in queue, {{ $failedInvoices }} flagged document(s).
                </p>
                <div class="d-flex gap-2">
                    <a href="{{ route('processing.index') }}" class="btn btn-sm btn-light border w-100 rounded-pill fw-medium text-dark">
                        <i class="bi bi-layers me-1"></i> Open Queue
                    </a>
                    @if($pendingInvoices > 0)
                        <form action="{{ route('processing.batch') }}" method="POST" class="w-100">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-teal w-100 rounded-pill shadow-sm">
                                <i class="bi bi-play-fill me-1"></i> Run Now
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- 3. System Ingestion Pipeline Card -->
        <div class="card border shadow-sm" style="border-color: var(--border-color) !important; background: #FFFFFF;">
            <div class="card-body p-3">
                <h6 class="fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.05em; color: var(--primary-navy);">
                    <i class="bi bi-diagram-3 me-1" style="color: var(--primary-teal);"></i> System Pipeline
                </h6>
                <ul class="list-unstyled small mb-0" style="color: var(--text-secondary);">
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle p-1 mt-1" style="background-color: var(--primary-teal);"></span>
                        <span><strong style="color: var(--text-primary);">1. Upload:</strong> Ingest multi-page PDFs or image scans with SHA-256 deduplication.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle p-1 bg-primary mt-1"></span>
                        <span><strong style="color: var(--text-primary);">2. AI Parse:</strong> Gemini AI & OCR extract vendor, buyer, items, and GST taxes.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle p-1 bg-warning mt-1"></span>
                        <span><strong style="color: var(--text-primary);">3. Reconcile:</strong> 5-level matching engine links invoices to shipping labels.</span>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <span class="badge rounded-circle p-1 bg-success mt-1"></span>
                        <span><strong style="color: var(--text-primary);">4. Export:</strong> Download formatted 2-sheet Excel workbooks (.xlsx).</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
