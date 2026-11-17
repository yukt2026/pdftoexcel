@props(['status'])

@php
    $normalized = strtolower($status ?? 'unknown');
    $colorClass = match($normalized) {
        'extracted', 'completed', 'paid', 'matched', 'exact_match', 'delivered', 'active' => 'badge-subtle-success',
        'validated', 'approved', 'within_tolerance' => 'badge-subtle-primary',
        'pending', 'processing', 'review_needed', 'partially_paid', 'in_transit' => 'badge-subtle-warning',
        'failed', 'mismatch', 'overdue', 'rejected', 'price_mismatch', 'quantity_mismatch' => 'badge-subtle-danger',
        default => 'badge-subtle-secondary',
    };
    $label = ucwords(str_replace('_', ' ', $normalized));
@endphp

<span class="badge {{ $colorClass }} px-2 py-1 rounded-pill fw-medium" style="font-size: 0.74rem;">
    {{ $label }}
</span>
