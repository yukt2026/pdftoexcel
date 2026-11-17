@extends('layouts.app')

@section('page_title', 'Upload Invoices')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Header Info -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Upload Invoice Documents</h4>
                <p class="text-muted small mb-0">Select multiple PDF invoices or drag and drop files. Extract them immediately and download Excel directly.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('exports.invoices') }}" class="btn btn-emerald btn-sm rounded-pill px-3 shadow-sm fw-semibold">
                    <i class="bi bi-file-earmark-excel me-1"></i> Quick Download Excel (All)
                </a>
                <a href="{{ route('batches.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-clock-history me-1"></i> View Batches
                </a>
            </div>
        </div>

        <!-- Upload Card -->
        <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data" id="uploadInvoiceForm">
                    @csrf

                    <!-- Batch Name (Optional) -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Batch Identifier / Name (Optional)</label>
                        <input type="text" name="batch_name" id="batch_name" class="form-control form-control-sm" placeholder="e.g. October Vendor Invoices - Batch A (or leave empty for auto-generation)">
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div class="border border-2 border-dashed rounded-4 p-5 text-center mb-3 bg-light position-relative" id="dropArea" style="cursor: pointer; border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                        <input type="file" name="documents[]" id="fileInput" class="d-none" multiple accept="application/pdf,.pdf">
                        <div class="d-inline-flex p-3 rounded-circle mb-3" style="background-color: var(--emerald-50); color: var(--emerald-600);">
                            <i class="bi bi-cloud-arrow-up fs-2"></i>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Click to browse or drag & drop invoice PDFs here</h6>
                        <div class="text-muted small">Supports multiple PDF files up to 50 MB each (Max 25 files per batch)</div>
                    </div>

                    <!-- Selected Files Queue Display -->
                    <div id="fileQueueContainer" class="d-none mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-secondary">
                                <i class="bi bi-list-check me-1 text-success"></i> Selected Files (<span id="selectedCount">0</span>)
                            </span>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none small" id="clearAllBtn">
                                Clear All
                            </button>
                        </div>
                        <div class="list-group shadow-sm rounded-3 overflow-hidden" id="fileListPreview" style="max-height: 280px; overflow-y: auto;">
                            <!-- Dynamically populated files -->
                        </div>
                    </div>

                    <!-- Duplicate & Error Alerts Container -->
                    <div id="uploadAlertContainer" class="d-none mb-3"></div>

                    <!-- Progress Bar (Visible during upload) -->
                    <div id="uploadProgressWrapper" class="d-none mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 small">
                            <span class="fw-semibold text-dark" id="progressStatusText">Uploading files...</span>
                            <span class="mono fw-bold text-success" id="progressPercentText">0%</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="uploadProgressBar" role="progressbar" style="width: 0%; background-color: var(--emerald-600) !important;"></div>
                        </div>
                    </div>

                    <!-- AI Pipeline Toggle -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light mb-4 border">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-cpu text-emerald-600 fs-5" style="color: var(--emerald-600);"></i>
                            <div>
                                <div class="fw-semibold small text-dark">Instant AI Extraction</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Parse header fields and line items immediately on submission</div>
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="process_immediately" id="process_immediately" value="1" checked>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-emerald py-2 fw-semibold rounded-pill shadow-sm" id="submitBtn" disabled>
                            <i class="bi bi-arrow-up-circle me-1"></i> Start PDF Batch Ingestion
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Uploads Table -->
        <div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold small text-uppercase text-secondary">
                    <i class="bi bi-clock-history me-1 text-success"></i> Recent Invoice Uploads
                </span>
                <div class="d-flex gap-2">
                    <a href="{{ route('batches.index') }}" class="btn btn-sm btn-light border text-decoration-none small">
                        All Batches
                    </a>
                    <a href="{{ route('processing.index') }}" class="btn btn-sm btn-link text-decoration-none small" style="color: var(--emerald-700);">
                        Queue Status
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>File Name</th>
                            <th>Batch</th>
                            <th>Size</th>
                            <th>Pages</th>
                            <th>Status</th>
                            <th>Linked Invoice</th>
                            <th class="text-end text-nowrap" style="min-width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentInvoiceUploads as $upload)
                            <tr>
                                <td class="fw-semibold text-dark">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                                        <div>
                                            <div>{{ $upload->original_filename }}</div>
                                            <span class="text-muted" style="font-size: 0.72rem;">{{ $upload->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($upload->batch)
                                        <a href="{{ route('batches.show', $upload->batch) }}" class="badge bg-light text-dark border text-decoration-none" title="View Batch">
                                            #{{ $upload->batch_id }} {{ Str::limit($upload->batch->batch_name, 16) }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $upload->formatted_size }}</td>
                                <td>{{ $upload->total_pages ?: $upload->page_count ?: 1 }} pgs</td>
                                <td>
                                    @php
                                        $st = $upload->extraction_status ?: $upload->status;
                                    @endphp
                                    <x-badge-status :status="$st" />
                                </td>
                                <td>
                                    @php
                                        $allInvoices = $upload->invoices->isNotEmpty() ? $upload->invoices : ($upload->invoice ? collect([$upload->invoice]) : collect());
                                    @endphp
                                    @if($allInvoices->isNotEmpty())
                                        @foreach($allInvoices as $inv)
                                            <div>
                                                <a href="{{ route('invoices.show', $inv) }}" class="fw-semibold text-decoration-none" style="color: var(--primary-teal);">
                                                    {{ $inv->invoice_number }}
                                                </a>
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('documents.preview', $upload) }}" target="_blank" class="btn btn-sm btn-light border" title="Preview PDF">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('documents.rawText', $upload) }}" class="btn btn-sm btn-light border text-dark" title="View Extracted Raw Text">
                                            <i class="bi bi-file-earmark-code"></i>
                                        </a>
                                        <a href="{{ route('documents.download', $upload) }}" class="btn btn-sm btn-light border text-primary" title="Download PDF">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        @if($upload->invoice)
                                            <a href="{{ route('invoices.export', $upload->invoice) }}" class="btn btn-sm btn-light border text-success" title="Download Excel for this Invoice">
                                                <i class="bi bi-file-earmark-excel"></i>
                                            </a>
                                            <a href="{{ route('invoices.show', $upload->invoice) }}" class="btn btn-sm btn-light border text-primary" title="View Invoice">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                        @elseif($st === 'uploaded' || $st === 'failed' || $st === 'pending')
                                            <form action="{{ route('documents.retry', $upload) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light border text-warning" title="Process/Retry File">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('documents.destroy', $upload) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this file?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No recent invoice PDFs uploaded yet.</td>
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
document.addEventListener('DOMContentLoaded', function () {
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const form = document.getElementById('uploadInvoiceForm');
    const previewContainer = document.getElementById('fileQueueContainer');
    const previewList = document.getElementById('fileListPreview');
    const selectedCountEl = document.getElementById('selectedCount');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const submitBtn = document.getElementById('submitBtn');
    const progressWrapper = document.getElementById('uploadProgressWrapper');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressPercentText = document.getElementById('progressPercentText');
    const progressStatusText = document.getElementById('progressStatusText');
    const alertContainer = document.getElementById('uploadAlertContainer');

    // Selected files state array: { file: File, id: string, hash: string|null, pageCount: number|null }
    let selectedFiles = [];

    // Trigger file chooser
    dropArea.addEventListener('click', () => fileInput.click());

    // Drag and drop event handlers
    ['dragenter', 'dragover'].forEach(evt => {
        dropArea.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.style.borderColor = 'var(--emerald-500)';
            dropArea.style.backgroundColor = 'var(--emerald-50)';
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropArea.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.style.borderColor = '#cbd5e1';
            dropArea.style.backgroundColor = '#f8fafc';
        });
    });

    dropArea.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    });

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    clearAllBtn.addEventListener('click', () => {
        selectedFiles = [];
        renderFileList();
    });

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // SHA-256 calculation in browser via Web Crypto API
    async function calculateSHA256(file) {
        const buffer = await file.arrayBuffer();
        const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    // Client-side PDF page counter heuristic from binary stream
    async function estimatePdfPages(file) {
        try {
            const buffer = await file.arrayBuffer();
            const text = new TextDecoder("latin1").decode(buffer);
            // Count /Type /Page (excluding /Pages)
            const matches = text.match(/\/Type\s*\/Page\b/g);
            if (matches && matches.length > 0) {
                return matches.length;
            }
            const countMatch = text.match(/\/Count\s+(\d+)/);
            if (countMatch) {
                return parseInt(countMatch[1], 10);
            }
        } catch (e) {
            console.warn('Page estimate error', e);
        }
        return 1;
    }

    async function handleFiles(newFiles) {
        alertContainer.classList.add('d-none');
        alertContainer.innerHTML = '';

        for (const file of newFiles) {
            // Strict PDF validation
            if (!file.name.toLowerCase().endsWith('.pdf') && file.type !== 'application/pdf') {
                showAlert(`'${file.name}' is not a PDF file. Only PDF files are accepted.`, 'danger');
                continue;
            }

            // Size validation (50 MB)
            if (file.size > 50 * 1024 * 1024) {
                showAlert(`'${file.name}' exceeds the maximum allowed size of 50 MB.`, 'warning');
                continue;
            }

            // Duplicate detection within current selection
            const alreadySelected = selectedFiles.some(f => f.file.name === file.name && f.file.size === file.size);
            if (alreadySelected) {
                showAlert(`'${file.name}' is already in your upload queue.`, 'info');
                continue;
            }

            const fileId = 'f_' + Math.random().toString(36).substring(2, 9);
            const fileItem = {
                id: fileId,
                file: file,
                hash: null,
                pageCount: 'Counting...',
                isDuplicate: false,
            };

            selectedFiles.push(fileItem);
            renderFileList();

            // Asynchronously compute hash and page count
            calculateSHA256(file).then(hash => {
                fileItem.hash = hash;
                // Check if duplicate exists within the batch
                const dupCount = selectedFiles.filter(f => f.hash === hash).length;
                if (dupCount > 1) {
                    fileItem.isDuplicate = true;
                }
                renderFileList();
            });

            estimatePdfPages(file).then(pages => {
                fileItem.pageCount = pages;
                renderFileList();
            });
        }

        renderFileList();
    }

    function removeFile(fileId) {
        selectedFiles = selectedFiles.filter(f => f.id !== fileId);
        renderFileList();
    }

    function renderFileList() {
        if (selectedFiles.length === 0) {
            previewContainer.classList.add('d-none');
            submitBtn.disabled = true;
            fileInput.value = '';
            return;
        }

        previewContainer.classList.remove('d-none');
        selectedCountEl.textContent = selectedFiles.length;
        submitBtn.disabled = false;

        previewList.innerHTML = '';
        selectedFiles.forEach((item, index) => {
            const li = document.createElement('div');
            li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-start-0 border-end-0';

            const hashShort = item.hash ? item.hash.substring(0, 8) + '...' : 'calculating...';
            const pageDisplay = typeof item.pageCount === 'number' ? `${item.pageCount} ${item.pageCount === 1 ? 'page' : 'pages'}` : item.pageCount;

            li.innerHTML = `
                <div class="d-flex align-items-center gap-3 text-truncate">
                    <span class="badge bg-light text-secondary border">${index + 1}</span>
                    <i class="bi bi-file-earmark-pdf text-danger fs-4"></i>
                    <div class="text-truncate">
                        <div class="fw-semibold text-dark text-truncate" style="max-width: 320px;">${item.file.name}</div>
                        <div class="text-muted d-flex gap-2 align-items-center" style="font-size: 0.72rem;">
                            <span><i class="bi bi-hdd me-1"></i>${formatBytes(item.file.size)}</span>
                            <span>&bull;</span>
                            <span class="text-primary fw-medium"><i class="bi bi-file-text me-1"></i>${pageDisplay}</span>
                            <span>&bull;</span>
                            <span class="mono text-secondary" title="${item.hash || ''}">SHA: ${hashShort}</span>
                            ${item.isDuplicate ? '<span class="badge bg-danger-subtle text-danger">Duplicate Hash</span>' : ''}
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-light border text-danger rounded-circle p-1" style="width: 28px; height: 28px;" title="Remove this file" data-remove-id="${item.id}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;

            li.querySelector('[data-remove-id]').addEventListener('click', (e) => {
                e.stopPropagation();
                removeFile(item.id);
            });

            previewList.appendChild(li);
        });
    }

    function showAlert(message, type = 'danger') {
        alertContainer.classList.remove('d-none');
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show small py-2 px-3 mb-2`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        alertContainer.appendChild(alertDiv);
    }

    // Ajax Form Submission with Upload Progress Indicator
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (selectedFiles.length === 0) {
            showAlert('Please select at least one PDF file to upload.');
            return;
        }

        const formData = new FormData();
        const csrfToken = document.querySelector('input[name="_token"]').value;
        formData.append('_token', csrfToken);

        const batchNameVal = document.getElementById('batch_name').value.trim();
        if (batchNameVal) {
            formData.append('batch_name', batchNameVal);
        }

        const autoProcessVal = document.getElementById('process_immediately').checked ? '1' : '0';
        formData.append('process_immediately', autoProcessVal);

        selectedFiles.forEach(item => {
            formData.append('documents[]', item.file, item.file.name);
        });

        // UI states during upload
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Ingesting Batch...';
        progressWrapper.classList.remove('d-none');
        progressBar.style.width = '0%';
        progressPercentText.textContent = '0%';
        progressStatusText.textContent = 'Uploading files to secure storage...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // Track upload progress
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressPercentText.textContent = percent + '%';
                if (percent === 100) {
                    progressStatusText.textContent = 'Processing PDF batch on server...';
                }
            }
        };

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    } else {
                        window.location.reload();
                    }
                } catch (err) {
                    window.location.reload();
                }
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i> Start PDF Batch Ingestion';
                progressWrapper.classList.add('d-none');

                try {
                    const errRes = JSON.parse(xhr.responseText);
                    if (errRes.errors && Array.isArray(errRes.errors)) {
                        errRes.errors.forEach(err => showAlert(err, 'danger'));
                    } else if (errRes.message) {
                        showAlert(errRes.message, 'danger');
                    } else {
                        showAlert('An error occurred during file upload. Please retry.', 'danger');
                    }

                    if (errRes.duplicates && errRes.duplicates.length > 0) {
                        errRes.duplicates.forEach(d => {
                            showAlert(`Duplicate file detected: '${d.filename}' was already uploaded previously as #${d.existing_id} (${d.existing_name}).`, 'warning');
                        });
                    }
                } catch (e) {
                    showAlert('Upload failed. Server responded with status ' + xhr.status, 'danger');
                }
            }
        };

        xhr.onerror = function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i> Start PDF Batch Ingestion';
            progressWrapper.classList.add('d-none');
            showAlert('Network error occurred during document upload. Please check your connection and retry.', 'danger');
        };

        xhr.send(formData);
    });
});
</script>
@endpush
