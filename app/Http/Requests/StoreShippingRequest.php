<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'document_type' => ['required', 'string', 'max:50'],
            'tracking_number' => ['required', 'string', 'max:100'],
            'carrier_name' => ['required', 'string', 'max:100'],
            'shipped_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'origin_address' => ['nullable', 'string'],
            'destination_address' => ['nullable', 'string'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'total_packages' => ['nullable', 'integer', 'min:1'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,in_transit,delivered,matched,discrepancy'],
        ];
    }
}
