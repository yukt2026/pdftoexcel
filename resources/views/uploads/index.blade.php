@extends('layouts.app')

@section('page_title', 'Upload Invoices & Documents')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 bg-primary-subtle text-primary rounded-circle mb-3">
                        <i class="bi bi-cloud-arrow-up fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Upload Invoices for AI Extraction</h4>
                    <p class="text-muted small">Upload PDF invoices, scans, or photos. Google Gemini AI & PDF Parser will automatically extract header fields and line items.</p>
                </div>

                <form action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                    @csrf

                    <div class="border border-2 border-dashed rounded-4 p-5 text-center mb-3 bg-light" id="dropArea" style="cursor: pointer; border-color: #cbd5e1 !important;">
                        <input type="file" name="documents[]" id="fileInput" class="d-none" multiple accept=".pdf,.png,.jpg,.jpeg">
                        <i class="bi bi-file-earmark-pdf fs-1 text-primary d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">Click to browse or drag and drop invoice files here</h6>
                        <div class="text-muted small">Supports PDF, PNG, JPG (up to 25 MB each)</div>
                        <div id="fileListPreview" class="mt-3 text-start d-none"></div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="process_immediately" id="process_immediately" value="1" checked>
                        <label class="form-check-label fw-medium small" for="process_immediately">
                            Process and extract immediately using AI & OCR pipeline
                        </label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold rounded-pill shadow-sm" id="submitBtn">
                            <i class="bi bi-cpu me-1"></i> Start Extraction Pipeline
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Uploads Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Upload History</span>
                <a href="{{ route('processing.index') }}" class="btn btn-sm btn-link text-decoration-none">View Processing Queue</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Status</th>
                            <th>Linked Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUploads as $upload)
                            <tr>
                                <td class="fw-medium text-dark">
                                    <i class="bi bi-file-earmark me-1 text-primary"></i>
                                    {{ $upload->original_filename }}
                                </td>
                                <td>{{ number_format($upload->file_size / 1024, 1) }} KB</td>
                                <td class="text-muted">{{ $upload->created_at->diffForHumans() }}</td>
                                <td><x-badge-status :status="$upload->status" /></td>
                                <td>
                                    @if($upload->invoice)
                                        <a href="{{ route('invoices.show', $upload->invoice) }}" class="text-decoration-none fw-semibold">
                                            {{ $upload->invoice->invoice_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">No documents uploaded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const fileListPreview = document.getElementById('fileListPreview');

    dropArea.addEventListener('click', () => fileInput.click());

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropArea.classList.add('bg-primary-subtle');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropArea.classList.remove('bg-primary-subtle');
        }, false);
    });

    dropArea.addEventListener('drop', (e) => {
        fileInput.files = e.dataTransfer.files;
        updateFileList();
    });

    fileInput.addEventListener('change', updateFileList);

    function updateFileList() {
        const files = fileInput.files;
        if (files.length > 0) {
            fileListPreview.classList.remove('d-none');
            fileListPreview.innerHTML = '<div class="fw-bold mb-1 small text-dark">Selected files (' + files.length + '):</div>';
            const ul = document.createElement('ul');
            ul.className = 'list-unstyled small mb-0';
            for (let i = 0; i < files.length; i++) {
                const li = document.createElement('li');
                li.innerHTML = '<i class="bi bi-file-earmark-check text-success me-1"></i> ' + files[i].name + ' (' + (files[i].size / 1024).toFixed(1) + ' KB)';
                ul.appendChild(li);
            }
            fileListPreview.appendChild(ul);
        } else {
            fileListPreview.classList.add('d-none');
        }
    }
</script>
@endpush
