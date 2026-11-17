<?php

namespace App\Services\Shipping;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\ShippingDocument;
use Exception;
use Illuminate\Support\Facades\DB;

class ShippingDocumentService
{
    /**
     * Create and record a shipping document (Waybill, Bill of Lading, Packing List).
     */
    public function createShippingDocument(array $data, ?int $userId = null): ShippingDocument
    {
        return DB::transaction(function () use ($data, $userId) {
            $shippingDoc = ShippingDocument::create([
                'user_id' => $userId ?? auth()->id() ?? 1,
                'invoice_id' => $data['invoice_id'] ?? null,
                'document_type' => $data['document_type'] ?? 'waybill',
                'tracking_number' => $data['tracking_number'] ?? null,
                'carrier_name' => $data['carrier_name'] ?? null,
                'shipped_date' => $data['shipped_date'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'origin_address' => $data['origin_address'] ?? null,
                'destination_address' => $data['destination_address'] ?? null,
                'total_weight' => $data['total_weight'] ?? null,
                'weight_unit' => $data['weight_unit'] ?? 'kg',
                'total_packages' => $data['total_packages'] ?? 1,
                'shipping_cost' => $data['shipping_cost'] ?? 0.0,
                'status' => $data['status'] ?? 'pending',
                'raw_data' => $data['raw_data'] ?? null,
            ]);

            ActivityLog::record(
                'shipping_document_created',
                "Created shipping document {$shippingDoc->tracking_number} ({$shippingDoc->carrier_name})",
                $shippingDoc
            );

            return $shippingDoc;
        });
    }

    /**
     * Link shipping document to an invoice.
     */
    public function linkToInvoice(ShippingDocument $document, Invoice $invoice): void
    {
        $document->update(['invoice_id' => $invoice->id]);
        
        ActivityLog::record(
            'shipping_linked_invoice',
            "Linked shipping doc {$document->tracking_number} to invoice {$invoice->invoice_number}",
            $document
        );
    }
}
