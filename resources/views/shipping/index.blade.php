@extends('layouts.app')

@section('page_title', 'Shipping Labels & Waybills')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Shipping Labels & Tracking Identifiers</h4>
        <p class="text-muted small mb-0">Extracted shipping labels across Flipkart E-Kart, Delhivery, and standard couriers for automated 2-way and 3-way invoice matching.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('uploads.shipping') }}" class="btn btn-emerald btn-sm px-3 rounded-pill shadow-sm">
            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Shipping Labels
        </a>
        <a href="{{ route('shipping.create') }}" class="btn btn-outline-emerald btn-sm px-3 rounded-pill">
            <i class="bi bi-plus-lg me-1"></i> Register Manual Waybill
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Search & Filters -->
<div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('shipping.index') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by AWB, Order No, Buyer or Courier..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="courier" class="form-select form-select-sm">
                    <option value="">All Courier Partners</option>
                    <option value="E-Kart" {{ request('courier') === 'E-Kart' ? 'selected' : '' }}>E-Kart Logistics</option>
                    <option value="Delhivery" {{ request('courier') === 'Delhivery' ? 'selected' : '' }}>Delhivery</option>
                    <option value="Blue Dart" {{ request('courier') === 'Blue Dart' ? 'selected' : '' }}>Blue Dart</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="format" class="form-select form-select-sm">
                    <option value="">All Label Formats</option>
                    <option value="flipkart_ekart" {{ request('format') === 'flipkart_ekart' ? 'selected' : '' }}>Flipkart E-Kart</option>
                    <option value="delhivery" {{ request('format') === 'delhivery' ? 'selected' : '' }}>Delhivery</option>
                    <option value="standard_courier" {{ request('format') === 'standard_courier' ? 'selected' : '' }}>Standard Courier</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark w-100 rounded-pill">Filter</button>
                <a href="{{ route('shipping.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Primary Extracted Shipping Labels Table -->
<div class="card shadow-sm border-0 mb-4" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam me-2 text-emerald"></i> Extracted Shipping Labels</h6>
        <span class="badge bg-light text-dark border">{{ $labels->total() }} Labels Found</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>AWB / Tracking #</th>
                    <th>Order #</th>
                    <th>Buyer Name</th>
                    <th>Courier</th>
                    <th>Marketplace</th>
                    <th>Destination</th>
                    <th>Pincode</th>
                    <th>Confidence</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($labels as $lbl)
                    <tr>
                        <td>
                            <a href="{{ route('shipping.labels.show', $lbl) }}" class="fw-bold mono text-dark text-decoration-none">
                                {{ $lbl->awb_number }}
                            </a>
                            @if($lbl->is_manually_corrected)
                                <span class="badge bg-warning-subtle text-warning border ms-1" style="font-size: 0.65rem;" title="Original: {{ $lbl->original_awb_number }}">
                                    <i class="bi bi-pencil-fill"></i> Edited
                                </span>
                            @endif
                        </td>
                        <td class="mono text-muted">{{ $lbl->order_number ?: 'N/A' }}</td>
                        <td class="fw-semibold text-dark">{{ $lbl->buyer_name ?: 'N/A' }}</td>
                        <td><span class="badge bg-light text-dark border"><i class="bi bi-truck me-1 text-emerald"></i>{{ $lbl->courier_name ?: 'Courier' }}</span></td>
                        <td><span class="text-muted">{{ $lbl->marketplace_name ?: 'E-Commerce' }}</span></td>
                        <td class="text-muted">{{ $lbl->state ?: 'N/A' }}</td>
                        <td class="mono">{{ $lbl->pincode ?: 'N/A' }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div class="progress" style="width: 45px; height: 5px;">
                                    <div class="progress-bar bg-emerald" style="width: {{ $lbl->confidence_score }}%"></div>
                                </div>
                                <span class="mono" style="font-size: 0.72rem;">{{ round($lbl->confidence_score) }}%</span>
                            </div>
                        </td>
                        <td>
                            @if($lbl->invoice)
                                <a href="{{ route('invoices.show', $lbl->invoice) }}" class="badge bg-success-subtle text-success border text-decoration-none">
                                    <i class="bi bi-link me-1"></i> {{ $lbl->invoice->invoice_number }}
                                </a>
                            @else
                                <span class="badge bg-light text-muted border">Unmatched</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('shipping.labels.show', $lbl) }}" class="btn btn-sm btn-light border p-1 px-2" title="Inspect / Correct AWB">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <form action="{{ route('shipping.labels.destroy', $lbl) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this shipping label?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger p-1 px-2" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam fs-1 text-secondary d-block mb-2"></i>
                            No shipping labels extracted yet. Upload a label PDF to begin.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($labels->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $labels->links() }}
        </div>
    @endif
</div>
@endsection
