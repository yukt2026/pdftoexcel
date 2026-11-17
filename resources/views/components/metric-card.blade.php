@props([
    'title' => 'Metric',
    'value' => '0',
    'icon' => 'bi-activity',
    'iconBg' => 'bg-light text-secondary',
    'subtext' => null,
    'badge' => null,
])

<div class="card h-100 border shadow-sm" style="border-color: var(--border-color) !important; border-radius: var(--card-radius); transition: all 0.15s ease-in-out;">
    <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em; color: var(--text-secondary);">
                {{ $title }}
            </span>
            <div class="rounded-3 d-flex align-items-center justify-content-center {{ $iconBg }}" style="width: 36px; height: 36px; flex-shrink: 0; font-size: 1rem;">
                <i class="bi {{ $icon }}"></i>
            </div>
        </div>
        <div>
            <div class="fw-bold" style="font-size: 1.65rem; color: var(--primary-navy); line-height: 1.15; letter-spacing: -0.02em;">
                {{ $value }}
            </div>
            @if($subtext)
                <div class="small mt-1 d-flex align-items-center gap-1" style="font-size: 0.75rem; color: var(--text-secondary);">
                    {{ $subtext }}
                </div>
            @endif
        </div>
    </div>
</div>
