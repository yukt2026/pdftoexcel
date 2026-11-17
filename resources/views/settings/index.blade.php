@extends('layouts.app')

@section('page_title', 'System Settings & Configuration')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">System Configuration & Settings</h4>
        <p class="text-muted small mb-0">Manage Gemini AI engine, OCR providers, storage thresholds, queue execution, and application branding.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="bi bi-people me-1"></i> User Management
        </a>
        <a href="{{ route('profile.index') }}" class="btn btn-light border btn-sm px-3 rounded-pill">
            <i class="bi bi-person-gear me-1"></i> Profile Settings
        </a>
        <button type="submit" form="settingsForm" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">
            <i class="bi bi-check2 me-1"></i> Save All Settings
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Main Settings Form (Left Column) -->
    <div class="col-lg-8">
        <form action="{{ route('settings.update') }}" method="POST" id="settingsForm">
            @csrf

            <!-- 1. Google Gemini API Configuration -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-stars text-warning me-2"></i> 1. Google Gemini AI Configuration
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Engine Active</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Google Gemini API Key</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" name="settings[gemini_api_key]" class="form-control form-control-sm" value="{{ $settings['gemini_api_key'] ?? '' }}" placeholder="AIzaSy...">
                            </div>
                            <div class="form-text" style="font-size: 0.72rem;">Stored encrypted in database. If blank, system falls back to heuristic PDF regex parser.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">AI Extraction Model</label>
                            @php $currModel = $settings['gemini_model'] ?? 'gemini-3.5-flash-lite'; @endphp
                            <select name="settings[gemini_model]" class="form-select form-select-sm">
                                <option value="gemini-3.5-flash-lite" {{ $currModel === 'gemini-3.5-flash-lite' ? 'selected' : '' }}>Gemini 3.5 Flash Lite (Ultra-fast, lowest latency, recommended)</option>
                                <option value="gemini-3.5-flash" {{ $currModel === 'gemini-3.5-flash' ? 'selected' : '' }}>Gemini 3.5 Flash (High accuracy, standard)</option>
                                <option value="gemini-3.8-flash" {{ $currModel === 'gemini-3.8-flash' ? 'selected' : '' }}>Gemini 3.8 Flash (Latest flagship multimodal)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Sampling Temperature</label>
                            <input type="number" step="0.05" min="0" max="1" name="settings[gemini_temperature]" class="form-control form-control-sm" value="{{ $settings['gemini_temperature'] ?? '0.10' }}">
                            <div class="form-text" style="font-size: 0.72rem;">Lower values (0.05 - 0.20) ensure deterministic JSON data extraction.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. OCR Configuration -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-eye text-primary me-2"></i> 2. OCR Configuration
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Primary OCR Provider</label>
                            @php $currOcr = $settings['ocr_engine'] ?? 'tesseract'; @endphp
                            <select name="settings[ocr_engine]" class="form-select form-select-sm">
                                <option value="tesseract" {{ $currOcr === 'tesseract' ? 'selected' : '' }}>Tesseract OCR (Local, offline engine)</option>
                                <option value="google_cloud_vision" {{ $currOcr === 'google_cloud_vision' ? 'selected' : '' }}>Google Cloud Vision API (Cloud engine)</option>
                                <option value="pdf_parser" {{ $currOcr === 'pdf_parser' ? 'selected' : '' }}>Native PDF Text Parser (No OCR rasterization)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Cloud Vision Credentials Path</label>
                            <input type="text" name="settings[ocr_credentials_path]" class="form-control form-control-sm" value="{{ $settings['ocr_credentials_path'] ?? '' }}" placeholder="storage/keys/google-cloud-vision.json">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Uploads & File Size Limits -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-cloud-arrow-up text-success me-2"></i> 3. Maximum PDF Upload Size & Batch Limits
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Max PDF Upload Size (MB)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="1" min="5" max="200" name="settings[max_upload_size_mb]" class="form-control form-control-sm" value="{{ $settings['max_upload_size_mb'] ?? '25' }}">
                                <span class="input-group-text bg-light">MB</span>
                            </div>
                            <div class="form-text" style="font-size: 0.72rem;">Maximum allowed file size per individual uploaded PDF.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Max Files Per Batch</label>
                            <input type="number" step="1" min="1" max="100" name="settings[max_files_per_batch]" class="form-control form-control-sm" value="{{ $settings['max_files_per_batch'] ?? '50' }}">
                            <div class="form-text" style="font-size: 0.72rem;">Maximum count of PDFs accepted in a single drag-and-drop batch.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Queue Processing Settings -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-stack text-indigo me-2" style="color: #6366f1;"></i> 4. Queue Processing Settings
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Queue Driver</label>
                            @php $currQueue = $settings['queue_connection'] ?? 'database'; @endphp
                            <select name="settings[queue_connection]" class="form-select form-select-sm">
                                <option value="database" {{ $currQueue === 'database' ? 'selected' : '' }}>Database (MySQL jobs table)</option>
                                <option value="redis" {{ $currQueue === 'redis' ? 'selected' : '' }}>Redis</option>
                                <option value="sync" {{ $currQueue === 'sync' ? 'selected' : '' }}>Sync (Immediate execution)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Job Timeout (Seconds)</label>
                            <input type="number" step="10" min="60" max="1800" name="settings[queue_timeout]" class="form-control form-control-sm" value="{{ $settings['queue_timeout'] ?? '300' }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Max Retry Attempts</label>
                            <input type="number" step="1" min="1" max="10" name="settings[queue_max_retries]" class="form-control form-control-sm" value="{{ $settings['queue_max_retries'] ?? '3' }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Default Excel Export Settings -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> 5. Default Excel Export Settings
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Default Filename Prefix</label>
                            <input type="text" name="settings[export_filename_prefix]" class="form-control form-control-sm" value="{{ $settings['export_filename_prefix'] ?? 'Invoices_Export' }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Workbook Format</label>
                            <input type="text" name="settings[export_default_format]" class="form-control form-control-sm" value="XLSX (Microsoft Excel 2007+)" disabled readonly>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="settings[export_auto_freeze_header]" value="1" id="freezeCheck" {{ ($settings['export_auto_freeze_header'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold text-dark" for="freezeCheck">Auto Freeze Header Rows (Pane A2)</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="settings[export_include_summary_sheet]" value="1" id="summaryCheck" {{ ($settings['export_include_summary_sheet'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold text-dark" for="summaryCheck">Include Sheet 2: Invoice Summary</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Application Name & Branding -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-palette text-danger me-2"></i> 6. Application Name & Branding
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Application Name</label>
                            <input type="text" name="settings[app_name]" class="form-control form-control-sm" value="{{ $settings['app_name'] ?? 'AI Invoice to Excel' }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Brand Primary Color</label>
                            <div class="input-group input-group-sm">
                                <input type="color" name="settings[brand_primary_color]" class="form-control form-control-color" value="{{ $settings['brand_primary_color'] ?? '#059669' }}" title="Choose brand color">
                                <input type="text" class="form-control form-control-sm mono" value="{{ $settings['brand_primary_color'] ?? '#059669' }}" readonly>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Organization / Company Name</label>
                            <input type="text" name="settings[company_name]" class="form-control form-control-sm" value="{{ $settings['company_name'] ?? 'Enterprise Operations' }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <button type="submit" class="btn btn-emerald px-4 rounded-pill shadow-sm fw-semibold">
                    <i class="bi bi-check2 me-1"></i> Save All Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: System Status, User Management & Quick Access -->
    <div class="col-lg-4">
        <!-- 7. Real-Time System Status -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-cpu text-success me-2"></i> 7. System Diagnostics & Status
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
            </div>
            <div class="card-body p-3 small">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">PHP Version:</span>
                    <span class="mono fw-semibold text-dark">{{ $systemStatus['php_version'] }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Laravel Framework:</span>
                    <span class="mono fw-semibold text-dark">{{ $systemStatus['laravel_version'] }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Database Engine:</span>
                    <span class="mono fw-semibold text-success">{{ $systemStatus['database_version'] }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Queue Pending Jobs:</span>
                    <span class="mono fw-bold text-dark">{{ $systemStatus['pending_jobs'] }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Queue Failed Jobs:</span>
                    <span class="mono fw-bold {{ $systemStatus['failed_jobs'] > 0 ? 'text-danger' : 'text-success' }}">{{ $systemStatus['failed_jobs'] }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Storage Directory:</span>
                    <span class="badge {{ $systemStatus['storage_writable'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                        {{ $systemStatus['storage_writable'] ? 'Writable' : 'Read Only' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Operating System:</span>
                    <span class="text-secondary mono" style="font-size: 0.72rem;">{{ $systemStatus['os'] }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Environment:</span>
                    <span class="badge bg-light text-dark border text-uppercase">{{ $systemStatus['environment'] }}</span>
                </div>
            </div>
        </div>

        <!-- 8. User Management Quick Summary -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-people text-primary me-2"></i> 8. User Management
                </span>
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" style="font-size: 0.72rem;">
                    Manage <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-3 small">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded border">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">Total Users</span>
                            <span class="fw-bold fs-6 text-dark mono">{{ $userMetrics['total_users'] }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded border">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">Admins</span>
                            <span class="fw-bold fs-6 text-primary mono">{{ $userMetrics['admins'] }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded border">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">Active</span>
                            <span class="fw-bold fs-6 text-success mono">{{ $userMetrics['active_users'] }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('users.index') }}" class="btn btn-light border btn-sm w-100 rounded-pill">
                    <i class="bi bi-person-plus me-1"></i> Add or Manage User Roles
                </a>
            </div>
        </div>

        <!-- 9. Profile Settings Quick Access -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                <i class="bi bi-person-gear text-secondary me-2"></i> 9. Profile & Security
            </div>
            <div class="card-body p-3 small">
                <p class="text-muted mb-3">
                    Currently logged in as <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->role }}). Update your email, change your password, or terminate remote sessions.
                </p>
                <a href="{{ route('profile.index') }}" class="btn btn-light border btn-sm w-100 rounded-pill">
                    <i class="bi bi-shield-lock me-1"></i> Open Profile & Security Settings
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
