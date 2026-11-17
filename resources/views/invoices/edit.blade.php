@extends('layouts.app')

@section('page_title', 'Review & Edit Invoice ' . ($invoice->invoice_number ?? 'Draft'))

@section('content')
@php
    $resolvedShippingId = $invoice->resolved_shipping_id;
@endphp

<form action="{{ route('invoices.update', $invoice) }}" method="POST" id="invoiceEditForm">
    @csrf
    @method('PUT')

    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="fw-bold mb-0 text-dark">Review & Edit Invoice</h4>
                <x-badge-status :status="$invoice->processing_status" />
                @if($invoice->is_manually_edited)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                        <i class="bi bi-pencil-fill me-1"></i> Previously Edited
                    </span>
                @endif
            </div>
            <p class="text-muted small mb-0">Audit AI-extracted fields, correct buyer address and taxes, adjust line items, and verify math integrity.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                Cancel
            </a>
            <button type="submit" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-check2-circle me-1"></i> Save Changes & Validate
            </button>
        </div>
    </div>

    <!-- Live Validation Alert Banner -->
    @if($validation['has_issues'])
        <div class="alert alert-warning border-0 shadow-sm mb-4 rounded-3" style="border-left: 4px solid var(--amber-500) !important;">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0 mt-1"></i>
                <div class="w-100">
                    <div class="fw-bold fs-6 text-dark mb-1">Validation Warnings Requiring Review:</div>
                    <ul class="mb-0 small text-secondary ps-3">
                        @if(!empty($validation['missing_fields']))
                            <li><strong>Missing Fields:</strong> {{ implode(', ', $validation['missing_fields']) }}</li>
                        @endif
                        @if($validation['is_low_confidence'])
                            <li><strong>Low AI Confidence:</strong> Confidence is {{ number_format($validation['confidence_score'], 0) }}% (less than 85% benchmark).</li>
                        @endif
                        @if($validation['is_unmatched_shipping'])
                            <li><strong>Unmatched Shipping:</strong> Shipping AWB is currently missing or unassigned.</li>
                        @endif
                        @if($validation['is_possible_duplicate'])
                            <li><strong>Possible Duplicate:</strong> Invoice #{{ $invoice->invoice_number }} already exists in system.</li>
                        @endif
                        @if($validation['has_tax_discrepancy'])
                            <li><strong>Tax Math Discrepancy:</strong> Calculated items sum differs from Grand Total.</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <!-- Main Form Column (Left) -->
        <div class="col-lg-7 col-xl-8">
            <!-- 1. Invoice Fields -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-receipt text-success me-2"></i> 1. Invoice Header Information
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">
                                Invoice Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="invoice_number" class="form-control form-control-sm @error('invoice_number') is-invalid @enderror" value="{{ old('invoice_number', $invoice->invoice_number) }}" required>
                            @error('invoice_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control form-control-sm" value="{{ old('invoice_date', $invoice->invoice_date?->format('Y-m-d')) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-control-sm" value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">PO Number</label>
                            <input type="text" name="po_number" class="form-control form-control-sm mono" value="{{ old('po_number', $invoice->po_number) }}" placeholder="e.g. 351974314617931328">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Order Number</label>
                            <input type="text" name="order_number" class="form-control form-control-sm mono" value="{{ old('order_number', $invoice->order_number) }}" placeholder="e.g. OD439142473682225100">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Place of Supply</label>
                            <input type="text" name="place_of_supply" class="form-control form-control-sm" value="{{ old('place_of_supply', $invoice->place_of_supply ?? $invoice->buyer_state) }}" placeholder="e.g. Karnataka (29)">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Currency</label>
                            <input type="text" name="currency" class="form-control form-control-sm" value="{{ old('currency', $invoice->currency ?? 'INR') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Payment Status</label>
                            <select name="payment_status" class="form-select form-select-sm">
                                <option value="unpaid" {{ old('payment_status', $invoice->payment_status) == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                <option value="partially_paid" {{ old('payment_status', $invoice->payment_status) == 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                                <option value="paid" {{ old('payment_status', $invoice->payment_status) == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="overdue" {{ old('payment_status', $invoice->payment_status) == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Processing Status</label>
                            <select name="processing_status" class="form-select form-select-sm">
                                <option value="validated" {{ old('processing_status', $invoice->processing_status) == 'validated' ? 'selected' : '' }}>Validated</option>
                                <option value="review_needed" {{ old('processing_status', $invoice->processing_status) == 'review_needed' ? 'selected' : '' }}>Review Needed</option>
                                <option value="extracted" {{ old('processing_status', $invoice->processing_status) == 'extracted' ? 'selected' : '' }}>Extracted</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Buyer & Shipping Information -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-person-badge text-primary me-2"></i> 2. Buyer Information & Shipping ID
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Buyer Name</label>
                            <input type="text" name="buyer_name" class="form-control form-control-sm" value="{{ old('buyer_name', $invoice->buyer_name ?? $invoice->customer?->name) }}" placeholder="Customer / Buyer Full Name">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Buyer State</label>
                            <input type="text" name="buyer_state" class="form-control form-control-sm" value="{{ old('buyer_state', $invoice->buyer_state) }}" placeholder="e.g. Karnataka">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Complete Buyer Address</label>
                            <textarea name="buyer_address" class="form-control form-control-sm" rows="2" placeholder="Street, Building, City, Pincode...">{{ old('buyer_address', $invoice->buyer_address ?? $invoice->customer?->address) }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">
                                Shipping ID / AWB Number
                                <span class="badge bg-light text-muted border ms-1">Courier Tracking</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="bi bi-barcode"></i></span>
                                <input type="text" name="shipping_id" class="form-control form-control-sm mono" value="{{ old('shipping_id', $resolvedShippingId) }}" placeholder="e.g. FMPC6756481216 or 1490843814423655">
                            </div>
                            <div class="form-text" style="font-size: 0.72rem;">Enter or correct courier AWB identifier to synchronize matching records.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Vendor / Supplier Name</label>
                            <input type="text" name="vendor_name" class="form-control form-control-sm" value="{{ old('vendor_name', $invoice->vendor?->name) }}" placeholder="Supplier Business Name">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Product Details (Dynamic Rows) -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-box-seam text-success me-2"></i> 3. Line Items & Product Details
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="addProductRow()">
                        <i class="bi bi-plus-lg me-1"></i> Add Product Row
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 small" id="itemsTable">
                        <thead class="table-light text-secondary">
                            <tr>
                                <th style="min-width: 180px;">Item Name / Description <span class="text-danger">*</span></th>
                                <th style="width: 80px;">Qty</th>
                                <th style="width: 100px;">Item Rate</th>
                                <th style="width: 85px;">Discount</th>
                                <th style="width: 105px;">Taxable</th>
                                <th style="width: 85px;">CGST</th>
                                <th style="width: 85px;">SGST</th>
                                <th style="width: 85px;">IGST</th>
                                <th style="width: 110px;">Total</th>
                                <th style="width: 45px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->items as $idx => $item)
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">
                                        <input type="text" name="items[{{ $idx }}][description]" class="form-control form-control-sm fw-medium" value="{{ $item->description }}" required>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <input type="text" name="items[{{ $idx }}][item_code]" class="form-control form-control-sm mono text-muted py-0" style="font-size: 0.7rem; width: 110px;" value="{{ $item->item_code }}" placeholder="SKU/Code">
                                            <div class="form-check form-check-inline mb-0" style="font-size: 0.7rem;">
                                                <input class="form-check-input" type="checkbox" name="items[{{ $idx }}][is_extra_charge]" value="1" id="extra_{{ $idx }}" {{ $item->is_extra_charge ? 'checked' : '' }}>
                                                <label class="form-check-label text-muted" for="extra_{{ $idx }}">Fee/Charge</label>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.001" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm qty-input text-center" value="{{ (float)$item->quantity }}" required oninput="calcItemRow(this)">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][item_rate]" class="form-control form-control-sm rate-input mono text-end" value="{{ (float)($item->item_rate ?: $item->unit_price) }}" required oninput="calcItemRow(this)">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][discount]" class="form-control form-control-sm discount-input mono text-end" value="{{ (float)$item->discount }}" oninput="calcItemRow(this)">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][taxable_value]" class="form-control form-control-sm taxable-input mono text-end fw-semibold" value="{{ (float)$item->taxable_value }}" required oninput="recalcInvoiceTotals()">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][cgst]" class="form-control form-control-sm cgst-input mono text-end" value="{{ (float)$item->cgst }}" oninput="calcTaxes(this)">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][sgst]" class="form-control form-control-sm sgst-input mono text-end" value="{{ (float)$item->sgst }}" oninput="calcTaxes(this)">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][igst]" class="form-control form-control-sm igst-input mono text-end" value="{{ (float)$item->igst }}" oninput="calcTaxes(this)">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][total_price]" class="form-control form-control-sm total-input mono text-end fw-bold text-dark" value="{{ (float)$item->total_price }}" required oninput="recalcInvoiceTotals()">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-light text-danger p-1" onclick="removeProductRow(this)" title="Delete Row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        No line items present. Click "Add Product Row" to create.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- 4. Tax Breakdown & Financial Totals -->
                <div class="card-footer bg-light p-4">
                    <div class="row justify-content-end">
                        <div class="col-md-6 col-lg-5">
                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">Taxable / Subtotal:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="taxable_value" id="taxable_value" class="form-control form-control-sm mono text-end" value="{{ (float)($invoice->subtotal ?: $invoice->items->sum('taxable_value')) }}" required oninput="recalcInvoiceTotals()">
                                </div>
                            </div>

                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">CGST Amount:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="cgst_amount" id="cgst_amount" class="form-control form-control-sm mono text-end" value="{{ (float)$invoice->cgst_amount }}" oninput="recalcInvoiceTotals()">
                                </div>
                            </div>

                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">SGST Amount:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="sgst_amount" id="sgst_amount" class="form-control form-control-sm mono text-end" value="{{ (float)$invoice->sgst_amount }}" oninput="recalcInvoiceTotals()">
                                </div>
                            </div>

                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">IGST Amount:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="igst_amount" id="igst_amount" class="form-control form-control-sm mono text-end" value="{{ (float)$invoice->igst_amount }}" oninput="recalcInvoiceTotals()">
                                </div>
                            </div>

                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">Shipping / Other:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="shipping_amount" id="shipping_amount" class="form-control form-control-sm mono text-end" value="{{ (float)$invoice->shipping_amount }}" oninput="recalcInvoiceTotals()">
                                </div>
                            </div>

                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">Discount:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="discount_amount" id="discount_amount" class="form-control form-control-sm mono text-end" value="{{ (float)$invoice->discount_amount }}" oninput="recalcInvoiceTotals()">
                                </div>
                            </div>

                            <hr class="my-2">

                            <div class="mb-2 row align-items-center">
                                <label class="col-sm-5 col-form-label col-form-label-sm fw-bold text-dark">Grand Total:</label>
                                <div class="col-sm-7">
                                    <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-control form-control-sm mono text-end fw-bold text-success fs-6" value="{{ (float)$invoice->total_amount }}" required>
                                </div>
                            </div>

                            <div id="liveMathWarning" class="alert alert-danger py-1 px-2 small mb-0 d-none">
                                <i class="bi bi-exclamation-triangle me-1"></i> Discrepancy: Items sum does not match Grand Total.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: PDF Preview, AI Original Data, and Previous Audit Log -->
        <div class="col-lg-5 col-xl-4">
            <!-- PDF Document Live Viewer -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-file-earmark-pdf text-danger me-2"></i> Original PDF Viewer
                    </span>
                    @if($invoice->document)
                        <a href="{{ route('documents.preview', $invoice->document) }}" target="_blank" class="btn btn-sm btn-light border p-1 px-2" title="Open PDF">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    @endif
                </div>
                <div class="card-body p-2 bg-light">
                    @if($invoice->document)
                        <div class="ratio ratio-4x3 border rounded bg-white" style="min-height: 400px;">
                            <iframe src="{{ route('documents.preview', $invoice->document) }}#toolbar=0" class="w-100 h-100 border-0 rounded" title="Original PDF"></iframe>
                        </div>
                    @else
                        <div class="py-4 text-center text-muted small">
                            No source PDF document linked.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Original AI Extracted Values Reference Drawer -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-robot text-primary me-2"></i> Original AI Values
                    </span>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-2" data-bs-toggle="collapse" data-bs-target="#aiOriginalValues">
                        Toggle
                    </button>
                </div>
                <div class="collapse show" id="aiOriginalValues">
                    <div class="card-body p-3 small bg-light">
                        <dl class="row mb-0">
                            <dt class="col-5 text-muted">Orig. Invoice #:</dt>
                            <dd class="col-7 fw-semibold mono">{{ $originalData['invoice']['invoice_number'] ?? 'N/A' }}</dd>

                            <dt class="col-5 text-muted">Orig. Date:</dt>
                            <dd class="col-7">{{ $originalData['invoice']['invoice_date'] ?? 'N/A' }}</dd>

                            <dt class="col-5 text-muted">Orig. PO #:</dt>
                            <dd class="col-7 mono">{{ $originalData['invoice']['po_number'] ?? 'N/A' }}</dd>

                            <dt class="col-5 text-muted">Orig. Order #:</dt>
                            <dd class="col-7 mono">{{ $originalData['invoice']['order_number'] ?? 'N/A' }}</dd>

                            <dt class="col-5 text-muted">Orig. Buyer:</dt>
                            <dd class="col-7">{{ $originalData['invoice']['buyer_name'] ?? 'N/A' }}</dd>

                            <dt class="col-5 text-muted">Orig. State:</dt>
                            <dd class="col-7">{{ $originalData['invoice']['buyer_state'] ?? 'N/A' }}</dd>

                            <dt class="col-5 text-muted">Orig. Total:</dt>
                            <dd class="col-7 fw-bold text-success mono">₹{{ number_format($originalData['invoice']['total_amount'] ?? $invoice->total_amount, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Previous Manual Corrections Log -->
            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
                <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                    <i class="bi bi-clock-history text-secondary me-2"></i> Previous Corrections ({{ $invoice->corrections->count() }})
                </div>
                <div class="card-body p-0 small">
                    @if($invoice->corrections->count() > 0)
                        <div class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
                            @foreach($invoice->corrections->take(10) as $corr)
                                <div class="list-group-item p-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark">{{ ucwords(str_replace('_', ' ', $corr->field_name)) }}</span>
                                        <span class="text-muted" style="font-size: 0.68rem;">{{ $corr->created_at->format('M d H:i') }}</span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        <span class="text-danger mono">{{ $corr->original_value ?: '(none)' }}</span> &rarr;
                                        <span class="text-success mono fw-semibold">{{ $corr->updated_value }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3 text-muted small">
                            No manual edits recorded yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let rowIndex = {{ $invoice->items->count() }};

    function addProductRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" name="items[${rowIndex}][description]" class="form-control form-control-sm fw-medium" placeholder="Item Name / Description" required>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <input type="text" name="items[${rowIndex}][item_code]" class="form-control form-control-sm mono text-muted py-0" style="font-size: 0.7rem; width: 110px;" placeholder="SKU/Code">
                    <div class="form-check form-check-inline mb-0" style="font-size: 0.7rem;">
                        <input class="form-check-input" type="checkbox" name="items[${rowIndex}][is_extra_charge]" value="1" id="extra_${rowIndex}">
                        <label class="form-check-label text-muted" for="extra_${rowIndex}">Fee/Charge</label>
                    </div>
                </div>
            </td>
            <td>
                <input type="number" step="0.001" name="items[${rowIndex}][quantity]" class="form-control form-control-sm qty-input text-center" value="1" required oninput="calcItemRow(this)">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][item_rate]" class="form-control form-control-sm rate-input mono text-end" value="0.00" required oninput="calcItemRow(this)">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][discount]" class="form-control form-control-sm discount-input mono text-end" value="0.00" oninput="calcItemRow(this)">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][taxable_value]" class="form-control form-control-sm taxable-input mono text-end fw-semibold" value="0.00" required oninput="recalcInvoiceTotals()">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][cgst]" class="form-control form-control-sm cgst-input mono text-end" value="0.00" oninput="calcTaxes(this)">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][sgst]" class="form-control form-control-sm sgst-input mono text-end" value="0.00" oninput="calcTaxes(this)">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][igst]" class="form-control form-control-sm igst-input mono text-end" value="0.00" oninput="calcTaxes(this)">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][total_price]" class="form-control form-control-sm total-input mono text-end fw-bold text-dark" value="0.00" required oninput="recalcInvoiceTotals()">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-light text-danger p-1" onclick="removeProductRow(this)" title="Delete Row">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        rowIndex++;
        recalcInvoiceTotals();
    }

    function removeProductRow(btn) {
        btn.closest('tr').remove();
        recalcInvoiceTotals();
    }

    function calcItemRow(input) {
        const tr = input.closest('tr');
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const rate = parseFloat(tr.querySelector('.rate-input').value) || 0;
        const discount = parseFloat(tr.querySelector('.discount-input').value) || 0;

        const taxable = Math.max(0, (qty * rate) - discount);
        tr.querySelector('.taxable-input').value = taxable.toFixed(2);

        calcTaxes(input);
    }

    function calcTaxes(input) {
        const tr = input.closest('tr');
        const taxable = parseFloat(tr.querySelector('.taxable-input').value) || 0;
        const cgst = parseFloat(tr.querySelector('.cgst-input').value) || 0;
        const sgst = parseFloat(tr.querySelector('.sgst-input').value) || 0;
        const igst = parseFloat(tr.querySelector('.igst-input').value) || 0;

        const total = taxable + cgst + sgst + igst;
        tr.querySelector('.total-input').value = total.toFixed(2);

        recalcInvoiceTotals();
    }

    function recalcInvoiceTotals() {
        let totalTaxable = 0;
        let totalCgst = 0;
        let totalSgst = 0;
        let totalIgst = 0;
        let itemsSum = 0;

        document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
            const taxableInput = tr.querySelector('.taxable-input');
            const cgstInput = tr.querySelector('.cgst-input');
            const sgstInput = tr.querySelector('.sgst-input');
            const igstInput = tr.querySelector('.igst-input');
            const totalInput = tr.querySelector('.total-input');

            if (taxableInput) totalTaxable += parseFloat(taxableInput.value) || 0;
            if (cgstInput) totalCgst += parseFloat(cgstInput.value) || 0;
            if (sgstInput) totalSgst += parseFloat(sgstInput.value) || 0;
            if (igstInput) totalIgst += parseFloat(igstInput.value) || 0;
            if (totalInput) itemsSum += parseFloat(totalInput.value) || 0;
        });

        // Set form totals if rows exist
        if (document.querySelectorAll('#itemsTable tbody tr').length > 0) {
            document.getElementById('taxable_value').value = totalTaxable.toFixed(2);
            document.getElementById('cgst_amount').value = totalCgst.toFixed(2);
            document.getElementById('sgst_amount').value = totalSgst.toFixed(2);
            document.getElementById('igst_amount').value = totalIgst.toFixed(2);
        }

        const currentTaxable = parseFloat(document.getElementById('taxable_value').value) || 0;
        const cgst = parseFloat(document.getElementById('cgst_amount').value) || 0;
        const sgst = parseFloat(document.getElementById('sgst_amount').value) || 0;
        const igst = parseFloat(document.getElementById('igst_amount').value) || 0;
        const shipping = parseFloat(document.getElementById('shipping_amount').value) || 0;
        const discount = parseFloat(document.getElementById('discount_amount').value) || 0;

        const calculatedGrandTotal = currentTaxable + cgst + sgst + igst + shipping - discount;
        document.getElementById('total_amount').value = calculatedGrandTotal.toFixed(2);

        // Discrepancy indicator
        const warning = document.getElementById('liveMathWarning');
        if (itemsSum > 0 && Math.abs(itemsSum - calculatedGrandTotal) > 1.0) {
            warning.classList.remove('d-none');
            warning.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Discrepancy: Items sum (${itemsSum.toFixed(2)}) differs from Grand Total (${calculatedGrandTotal.toFixed(2)}).`;
        } else {
            warning.classList.add('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        recalcInvoiceTotals();
    });
</script>
@endpush
