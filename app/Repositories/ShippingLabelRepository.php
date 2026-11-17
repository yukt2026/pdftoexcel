<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\ShippingLabel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ShippingLabelRepository
{
    /**
     * Create a new shipping label record.
     */
    public function create(array $data): ShippingLabel
    {
        return ShippingLabel::create($data);
    }

    /**
     * Update an existing shipping label.
     */
    public function update(ShippingLabel $label, array $data): ShippingLabel
    {
        $label->update($data);
        return $label->fresh();
    }

    /**
     * Allow manual correction of an extracted shipping ID / AWB number.
     * Preserves the original identifier for full auditability.
     */
    public function updateAwb(ShippingLabel $label, string $newAwb, ?string $reason = null): ShippingLabel
    {
        return $label->correctAwb($newAwb, $reason);
    }

    /**
     * Find a shipping label by its primary ID.
     */
    public function findById(int $id): ?ShippingLabel
    {
        return ShippingLabel::with(['user', 'document', 'invoice'])->find($id);
    }

    /**
     * Find a shipping label by its AWB / tracking number.
     */
    public function findByAwb(string $awb, ?int $userId = null): ?ShippingLabel
    {
        $query = ShippingLabel::where('awb_number', trim($awb));
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return $query->first();
    }

    /**
     * Find a shipping label by Order Number.
     */
    public function findByOrderNumber(string $orderNumber, ?int $userId = null): ?ShippingLabel
    {
        $query = ShippingLabel::where('order_number', trim($orderNumber));
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return $query->first();
    }

    /**
     * Get paginated shipping labels for a specific user with filtering.
     */
    public function getUserLabels(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ShippingLabel::where('user_id', $userId)->with(['document', 'invoice'])->latest();
        return $this->applyFilters($query, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Get all shipping labels (Admin view) with filtering.
     */
    public function getAllLabels(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ShippingLabel::with(['user', 'document', 'invoice'])->latest();
        return $this->applyFilters($query, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Associate a shipping label with an invoice (matching).
     */
    public function matchWithInvoice(ShippingLabel $label, int $invoiceId): bool
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $label->update([
            'invoice_id' => $invoice->id,
            'status' => ShippingLabel::STATUS_MATCHED,
        ]);

        ActivityLog::record(
            'shipping_label_matched',
            "Shipping Label {$label->awb_number} matched to Invoice {$invoice->invoice_number}.",
            $label,
            ['invoice_id' => $invoice->id, 'order_number' => $label->order_number]
        );

        return true;
    }

    /**
     * Delete a shipping label.
     */
    public function delete(ShippingLabel $label): bool
    {
        return (bool) $label->delete();
    }

    /**
     * Apply common query filters.
     */
    protected function applyFilters($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['courier'])) {
            $query->courier($filters['courier']);
        }

        if (!empty($filters['format'])) {
            $query->where('label_format', $filters['format']);
        }

        if (!empty($filters['is_corrected'])) {
            $query->where('is_manually_corrected', true);
        }

        return $query;
    }
}
