<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');
        if (!$invoice) {
            return false;
        }

        return $this->user()?->can('update', $invoice) ?? false;
    }

    public function rules(): array
    {
        return [
            // Core Invoice Identifiers
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'shipping_id' => ['nullable', 'string', 'max:100'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],

            // Buyer Information
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string'],
            'buyer_state' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],

            // Vendor / Supplier Information
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_tax_number' => ['nullable', 'string', 'max:60'],

            // Tax & Amounts Breakdown
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'taxable_value' => ['nullable', 'numeric', 'min:0'],
            'cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'igst_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],

            // Statuses
            'payment_status' => ['nullable', 'in:unpaid,partially_paid,paid,overdue'],
            'processing_status' => ['nullable', 'in:pending,processing,extracted,review_needed,validated,exported,failed'],

            // Line Items
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxable_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst' => ['nullable', 'numeric', 'min:0'],
            'items.*.igst' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['required', 'numeric'],
            'items.*.is_extra_charge' => ['nullable', 'boolean'],
        ];
    }
}
