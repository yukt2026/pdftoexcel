@extends('layouts.app')

@section('page_title', 'Register Shipping Bill')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-truck text-success me-2"></i> Register Waybill / Carrier Document</span>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('shipping.store') }}" method="POST">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Document Type</label>
                            <select name="document_type" class="form-select form-select-sm" required>
                                <option value="waybill">Air / Road Waybill</option>
                                <option value="bill_of_lading">Ocean Bill of Lading</option>
                                <option value="packing_list">Packing Slip / List</option>
                                <option value="delivery_challan">Delivery Challan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Tracking / AWB Number</label>
                            <input type="text" name="tracking_number" class="form-control form-control-sm" required placeholder="e.g. 794829104829">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Carrier / Courier Name</label>
                            <input type="text" name="carrier_name" class="form-control form-control-sm" required placeholder="e.g. FedEx, DHL, UPS, Maersk">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Link to Existing Invoice (Optional)</label>
                            <select name="invoice_id" class="form-select form-select-sm">
                                <option value="">-- Unlinked / Match Later --</option>
                                @foreach($invoices as $inv)
                                    <option value="{{ $inv->id }}">{{ $inv->invoice_number }} ({{ $inv->vendor?->name ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Shipped Date</label>
                            <input type="date" name="shipped_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Total Packages</label>
                            <input type="number" name="total_packages" class="form-control form-control-sm" value="1" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Total Weight (kg)</label>
                            <input type="number" step="0.01" name="total_weight" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Freight Charge (₹)</label>
                            <input type="number" step="0.01" name="shipping_cost" class="form-control form-control-sm" value="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Delivery Status</label>
                            <select name="status" class="form-select form-select-sm" required>
                                <option value="pending">Pending</option>
                                <option value="in_transit">In Transit</option>
                                <option value="delivered" selected>Delivered</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('shipping.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">Cancel</a>
                        <button type="submit" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">Save Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
