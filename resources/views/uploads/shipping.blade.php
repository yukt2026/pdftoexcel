@extends('layouts.app')

@section('page_title', 'Upload Shipping Labels & Waybills')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Header Info -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Upload Shipping Documents & Labels</h4>
                <p class="text-muted small mb-0">Upload multi-page Waybills, Bills of Lading, and Shipping Labels (PDF only) for automated cross-referencing and invoice matching.</p>
            </div>
            <div>
                <a href="{{ route('batches.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-clock-history me-1"></i> View Upload Batches
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('uploads.shipping.store') }}" method="POST" enctype="multipart/form-data" id="uploadShippingForm">
                    @csrf

                    <!-- Batch Identifier (Optional) -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Batch Identifier / Name (Optional)</label>
                        <input type="text" name="batch_name" id="shipping_batch_name" class="form-control form-control-sm" placeholder="e.g. FedEx Waybills - November Batch 1 (or leave blank)">
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div class="border border-2 border-dashed rounded-4 p-5 text-center mb-3 bg-light" id="shippingDropArea" style="cursor: pointer; border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                        <input type="file" name="shipping_files[]" id="shippingFileInput" class="d-none" multiple accept="application/pdf,.pdf">
                        <div class="d-inline-flex p-3 rounded-circle mb-3" style="background-color: var(--emerald-50); color: var(--emerald-600);">
                            <i class="bi bi-truck fs-2"></i>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Click to browse or drag & drop shipping label PDFs here</h6>
                        <div class="text-muted small">Supports PDF documents up to 50 MB each (Max 25 files per batch)</div>
                    </div>

                    <!-- Selected Files Queue Display -->
                    <div id="shippingFileQueueContainer" class="d-none mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-secondary">
                                <i class="bi bi-list-check me-1 text-success"></i> Selected Shipping PDFs (<span id="shippingSelectedCount">0</span>)
                            </span>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none small" id="shippingClearAllBtn">
                                Clear All
                            </button>
                        </div>
                        <div class="list-group shadow-sm rounded-3 overflow-hidden" id="shippingFileListPreview" style="max-height: 280px; overflow-y: auto;">
                            <!-- Dynamically populated files -->
                        </div>
                    </div>

                    <!-- Duplicate & Error Alerts Container -->
                    <div id="shippingUploadAlertContainer" class="d-none mb-3"></div>

                    <!-- Progress Bar (Visible during upload) -->
                    <div id="shippingProgressWrapper" class="d-none mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 small">
                            <span class="fw-semibold text-dark" id="shippingProgressStatusText">Uploading files...</span>
                            <span class="mono fw-bold text-success" id="shippingProgressPercentText">0%</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="shippingProgressBar" role="progressbar" style="width: 0%; background-color: var(--emerald-600) !important;"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Carrier / Courier Name</label>
                            <input type="text" name="carrier_name" class="form-control form-control-sm" placeholder="e.g. FedEx Express, DHL, UPS, Maersk">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Link to Specific Invoice (Optional)</label>
                            <select name="invoice_id" class="form-select form-select-sm">
                                <option value="">-- Auto-Detect or Match Later --</option>
                                @foreach($invoices as $inv)
                                    <option value="{{ $inv->id }}">{{ $inv->invoice_number }} - {{ $inv->vendor?->name ?? 'N/A' }} (₹{{ number_format($inv->total_amount, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-emerald py-2 fw-semibold rounded-pill shadow-sm" id="shippingSubmitBtn" disabled>
                            <i class="bi bi-cloud-arrow-up me-1"></i> Start Shipping Labels Batch Ingestion
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Shipping Uploads -->
        <div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold small text-uppercase text-secondary">
                    <i class="bi bi-clock-history me-1 text-success"></i> Recent Shipping Documents
                </span>
                <div class="d-flex gap-2">
                    <a href="{{ route('batches.index') }}" class="btn btn-sm btn-light border text-decoration-none small">
                        All Batches
                    </a>
                    <a href="{{ route('shipping.index') }}" class="btn btn-sm btn-link text-decoration-none small" style="color: var(--emerald-700);">
                        View All Waybills
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>Tracking #</th>
                            <th>Carrier</th>
                            <th>Status</th>
                            <th>Freight Cost</th>
                            <th>Linked Invoice</th>
                            <th>Date</th>
                            <th class="text-end text-nowrap" style="min-width: 110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentShippingUploads as $doc)
                            <tr>
                                <td class="fw-semibold mono text-dark">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                    {{ $doc->tracking_number }}
                                </td>
                                <td><i class="bi bi-truck text-muted me-1"></i> {{ $doc->carrier_name }}</td>
                                <td><x-badge-status :status="$doc->status" /></td>
                                <td class="mono fw-semibold">₹{{ number_format($doc->shipping_cost, 2) }}</td>
                                <td>
                                    @if($doc->invoice)
                                        <a href="{{ route('invoices.show', $doc->invoice) }}" class="fw-semibold text-decoration-none" style="color: var(--emerald-700);">
                                            {{ $doc->invoice->invoice_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">Unlinked</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $doc->created_at->format('M d, Y') }}</td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('shipping.show', $doc) }}" class="btn btn-sm btn-light border" title="View Waybill">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form action="{{ route('shipping.destroy', $doc) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this shipping record?');">
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
                                <td colspan="7" class="text-center py-4 text-muted">No shipping documents uploaded yet.</td>
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
    const dropArea = document.getElementById('shippingDropArea');
    const fileInput = document.getElementById('shippingFileInput');
    const form = document.getElementById('uploadShippingForm');
    const previewContainer = document.getElementById('shippingFileQueueContainer');
    const previewList = document.getElementById('shippingFileListPreview');
    const selectedCountEl = document.getElementById('shippingSelectedCount');
    const clearAllBtn = document.getElementById('shippingClearAllBtn');
    const submitBtn = document.getElementById('shippingSubmitBtn');
    const progressWrapper = document.getElementById('shippingProgressWrapper');
    const progressBar = document.getElementById('shippingProgressBar');
    const progressPercentText = document.getElementById('shippingProgressPercentText');
    const progressStatusText = document.getElementById('shippingProgressStatusText');
    const alertContainer = document.getElementById('shippingUploadAlertContainer');

    let selectedFiles = [];

    dropArea.addEventListener('click', () => fileInput.click());

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
        handleFiles(dt.files);
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

    async function calculateSHA256(file) {
        const buffer = await file.arrayBuffer();
        const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function estimatePdfPages(file) {
        try {
            const buffer = await file.arrayBuffer();
            const text = new TextDecoder("latin1").decode(buffer);
            const matches = text.match(/\/Type\s*\/Page\b/g);
            if (matches && matches.length > 0) return matches.length;
            const countMatch = text.match(/\/Count\s+(\d+)/);
            if (countMatch) return parseInt(countMatch[1], 10);
        } catch (e) {
            console.warn(e);
        }
        return 1;
    }

    async function handleFiles(newFiles) {
        alertContainer.classList.add('d-none');
        alertContainer.innerHTML = '';

        for (const file of newFiles) {
            if (!file.name.toLowerCase().endsWith('.pdf') && file.type !== 'application/pdf') {
                showAlert(`'${file.name}' is not a PDF file. Shipping documents must be PDF format.`, 'danger');
                continue;
            }

            if (file.size > 50 * 1024 * 1024) {
                showAlert(`'${file.name}' exceeds the 50 MB limit.`, 'warning');
                continue;
            }

            const alreadySelected = selectedFiles.some(f => f.file.name === file.name && f.file.size === file.size);
            if (alreadySelected) {
                showAlert(`'${file.name}' is already in your selection list.`, 'info');
                continue;
            }

            const fileId = 'sh_' + Math.random().toString(36).substring(2, 9);
            const fileItem = {
                id: fileId,
                file: file,
                hash: null,
                pageCount: 'Counting...',
                isDuplicate: false,
            };

            selectedFiles.push(fileItem);
            renderFileList();

            calculateSHA256(file).then(hash => {
                fileItem.hash = hash;
                const dupCount = selectedFiles.filter(f => f.hash === hash).length;
                if (dupCount > 1) fileItem.isDuplicate = true;
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

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (selectedFiles.length === 0) {
            showAlert('Please select at least one shipping label PDF to upload.');
            return;
        }

        const formData = new FormData(form);
        formData.delete('shipping_files[]');

        selectedFiles.forEach(item => {
            formData.append('shipping_files[]', item.file, item.file.name);
        });

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Ingesting Shipping Batch...';
        progressWrapper.classList.remove('d-none');
        progressBar.style.width = '0%';
        progressPercentText.textContent = '0%';
        progressStatusText.textContent = 'Uploading shipping documents...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressPercentText.textContent = percent + '%';
                if (percent === 100) {
                    progressStatusText.textContent = 'Processing shipping documents...';
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
                submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Start Shipping Labels Batch Ingestion';
                progressWrapper.classList.add('d-none');

                try {
                    const errRes = JSON.parse(xhr.responseText);
                    if (errRes.errors && Array.isArray(errRes.errors)) {
                        errRes.errors.forEach(err => showAlert(err, 'danger'));
                    } else if (errRes.message) {
                        showAlert(errRes.message, 'danger');
                    } else {
                        showAlert('Error during shipping label upload. Please retry.', 'danger');
                    }

                    if (errRes.duplicates && errRes.duplicates.length > 0) {
                        errRes.duplicates.forEach(d => {
                            showAlert(`Duplicate file skipped: '${d.filename}'.`, 'warning');
                        });
                    }
                } catch (e) {
                    showAlert('Upload failed. Server responded with code ' + xhr.status, 'danger');
                }
            }
        };

        xhr.onerror = function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Start Shipping Labels Batch Ingestion';
            progressWrapper.classList.add('d-none');
            showAlert('Network error occurred during shipping labels upload.', 'danger');
        };

        xhr.send(formData);
    });
});
</script>
@endpush
