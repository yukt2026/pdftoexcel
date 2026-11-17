<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class ShippingUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isActive();
    }

    public function rules(): array
    {
        $maxKb = config('invoice_parser.max_upload_size_kb', 51200);
        $maxFiles = config('invoice_parser.max_batch_files', 25);

        return [
            'batch_name' => ['nullable', 'string', 'max:150'],
            'carrier_name' => ['nullable', 'string', 'max:100'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'shipping_files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'shipping_files.*' => ['required', 'file', 'mimes:pdf', "max:{$maxKb}"],
        ];
    }

    public function messages(): array
    {
        $maxMb = round(config('invoice_parser.max_upload_size_kb', 51200) / 1024);
        $maxFiles = config('invoice_parser.max_batch_files', 25);

        return [
            'shipping_files.required' => 'Please select at least one shipping label PDF file to upload.',
            'shipping_files.array' => 'Uploaded shipping labels must be provided as a file list.',
            'shipping_files.min' => 'Please select at least one shipping label PDF file.',
            'shipping_files.max' => "You can upload a maximum of {$maxFiles} shipping PDFs per batch.",
            'shipping_files.*.required' => 'A valid file is required for each upload item.',
            'shipping_files.*.file' => 'The selected shipping document is invalid.',
            'shipping_files.*.mimes' => 'Only PDF files are supported for shipping labels. Please upload .pdf documents.',
            'shipping_files.*.max' => "Shipping label PDF must not exceed {$maxMb} MB.",
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if ($this->filled('invoice_id')) {
                $invoice = Invoice::find($this->invoice_id);
                if ($invoice && !auth()->user()->isAdmin() && $invoice->user_id !== auth()->id()) {
                    $v->errors()->add('invoice_id', 'Unauthorized. You cannot link shipping documents to invoices owned by another user.');
                }
            }
        });
    }
}
