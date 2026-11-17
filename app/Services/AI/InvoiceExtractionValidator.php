<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class InvoiceExtractionValidator
{
    /**
     * Validate and sanitize structured invoice extraction payload.
     *
     * @param array $payload
     * @return array{is_valid: bool, data: array, errors: array, warnings: array}
     */
    public function validate(array $payload): array
    {
        $errors = [];
        $warnings = [];
        $data = $payload;

        // 1. Validate Invoice Level Fields
        $invoice = $data['invoice'] ?? [];
        if (!is_array($invoice)) {
            $errors[] = "Missing 'invoice' object in extraction payload.";
            $invoice = [];
        }

        $sanitizedInvoice = [
            'invoice_date' => $this->cleanString($invoice['invoice_date'] ?? null),
            'invoice_number' => $this->cleanString($invoice['invoice_number'] ?? null),
            'po_number' => $this->cleanString($invoice['po_number'] ?? null),
            'order_number' => $this->cleanString($invoice['order_number'] ?? null),
            'buyer_name' => $this->cleanString($invoice['buyer_name'] ?? null),
            'address' => $this->cleanString($invoice['address'] ?? $invoice['buyer_address'] ?? null),
            'buyer_address' => $this->cleanString($invoice['buyer_address'] ?? $invoice['address'] ?? null),
            'state' => $this->cleanString($invoice['state'] ?? $invoice['buyer_state'] ?? null),
            'buyer_state' => $this->cleanString($invoice['buyer_state'] ?? $invoice['state'] ?? null),
            'place_of_supply' => $this->cleanString($invoice['place_of_supply'] ?? $invoice['state'] ?? null),
        ];

        if (empty($sanitizedInvoice['invoice_number']) && empty($sanitizedInvoice['order_number'])) {
            $warnings[] = "Neither Invoice Number nor Order Number was identified in document.";
        }

        if (empty($sanitizedInvoice['buyer_name'])) {
            $warnings[] = "Buyer/Customer name is missing or could not be determined.";
        }

        if (empty($sanitizedInvoice['address'])) {
            $warnings[] = "Shipping/Customer address is missing.";
        }

        // 2. Validate Product Level Items
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $errors[] = "Invoice must contain at least one product item.";
            $items = [];
        }

        $sanitizedItems = [];
        $totalTaxable = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalAmount = 0.0;

        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemName = $this->cleanString($item['item_name'] ?? null);
            if (empty($itemName)) {
                $itemName = "Item #" . ($idx + 1);
                $warnings[] = "Item at position " . ($idx + 1) . " has no description/name.";
            }

            $itemRate = $this->cleanFloat($item['item_rate'] ?? 0.0);
            $discount = $this->cleanFloat($item['discount'] ?? 0.0);
            $taxableValue = $this->cleanFloat($item['taxable_value'] ?? 0.0);
            $cgst = $this->cleanFloat($item['cgst'] ?? 0.0);
            $sgst = $this->cleanFloat($item['sgst'] ?? 0.0);
            $igst = $this->cleanFloat($item['igst'] ?? 0.0);
            $total = $this->cleanFloat($item['total'] ?? 0.0);

            // If line total is missing, fallback to rate or taxable + tax without forcing calculation overwrite
            if ($total <= 0.0) {
                if ($taxableValue > 0) {
                    $total = $taxableValue + $cgst + $sgst + $igst;
                } elseif ($itemRate > 0) {
                    $total = $itemRate;
                }
            }

            $totalTaxable += $taxableValue;
            $totalCgst += $cgst;
            $totalSgst += $sgst;
            $totalIgst += $igst;
            $totalAmount += $total;

            $sanitizedItems[] = [
                'item_name' => $itemName,
                'item_code' => $this->cleanString($item['item_code'] ?? null),
                'hsn_code' => $this->cleanString($item['hsn_code'] ?? null),
                'quantity' => max(1.0, (float) ($item['quantity'] ?? 1.0)),
                'item_rate' => $itemRate,
                'discount' => $discount,
                'taxable_value' => $taxableValue,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'total' => $total,
                'is_extra_charge' => (bool) ($item['is_extra_charge'] ?? false),
                'confidence' => (float) ($item['confidence'] ?? 90.0),
                'page_number' => (int) ($item['page_number'] ?? 1),
            ];
        }

        // 3. Totals Handling
        $reportedTotals = $data['totals'] ?? [];
        $sanitizedTotals = [
            'total_taxable_value' => $this->cleanFloat($reportedTotals['total_taxable_value'] ?? $totalTaxable),
            'total_cgst' => $this->cleanFloat($reportedTotals['total_cgst'] ?? $totalCgst),
            'total_sgst' => $this->cleanFloat($reportedTotals['total_sgst'] ?? $totalSgst),
            'total_igst' => $this->cleanFloat($reportedTotals['total_igst'] ?? $totalIgst),
            'total_amount' => $this->cleanFloat($reportedTotals['total_amount'] ?? $totalAmount),
        ];

        // 4. Confidence Scores
        $confidenceScores = $data['confidence_scores'] ?? [];
        $sanitizedConfidence = [
            'invoice_date' => (float) ($confidenceScores['invoice_date'] ?? 90.0),
            'invoice_number' => (float) ($confidenceScores['invoice_number'] ?? 90.0),
            'po_number' => (float) ($confidenceScores['po_number'] ?? 85.0),
            'order_number' => (float) ($confidenceScores['order_number'] ?? 90.0),
            'buyer_name' => (float) ($confidenceScores['buyer_name'] ?? 90.0),
            'address' => (float) ($confidenceScores['address'] ?? 85.0),
            'state' => (float) ($confidenceScores['state'] ?? 90.0),
            'overall' => (float) ($confidenceScores['overall'] ?? 90.0),
        ];

        // 5. Seller Information (Preserved)
        $seller = $data['seller'] ?? [];
        $sanitizedSeller = [
            'name' => $this->cleanString($seller['name'] ?? null),
            'gstin' => $this->cleanString($seller['gstin'] ?? null),
            'pan' => $this->cleanString($seller['pan'] ?? null),
            'address' => $this->cleanString($seller['address'] ?? null),
            'state' => $this->cleanString($seller['state'] ?? null),
        ];

        $cleanData = [
            'invoice' => $sanitizedInvoice,
            'seller' => $sanitizedSeller,
            'items' => $sanitizedItems,
            'products' => $sanitizedItems,
            'totals' => $sanitizedTotals,
            'confidence_scores' => $sanitizedConfidence,
            'notes' => $this->cleanString($data['notes'] ?? null),
        ];

        $isValid = count($errors) === 0;

        return [
            'is_valid' => $isValid,
            'data' => $cleanData,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Clean string value, converting empty strings or whitespace to null.
     */
    protected function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return ($trimmed === '' || strtolower($trimmed) === 'null' || strtolower($trimmed) === 'n/a') ? null : $trimmed;
    }

    /**
     * Parse and clean float representation (handles currency symbols, commas).
     */
    protected function cleanFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (is_string($value)) {
            // Remove Rupee symbol, currency codes, spaces, and commas
            $clean = preg_replace('/[^\d.-]/', '', str_replace(',', '', $value));
            if (is_numeric($clean)) {
                return round((float) $clean, 2);
            }
        }

        return 0.00;
    }
}
