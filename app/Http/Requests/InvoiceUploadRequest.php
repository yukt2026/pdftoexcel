<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceUploadRequest extends FormRequest
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
            'documents' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'documents.*' => ['required', 'file', 'mimes:pdf', "max:{$maxKb}"],
            'process_immediately' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $maxMb = round(config('invoice_parser.max_upload_size_kb', 51200) / 1024);
        $maxFiles = config('invoice_parser.max_batch_files', 25);

        return [
            'documents.required' => 'Please select at least one invoice PDF file to upload.',
            'documents.array' => 'Uploaded documents must be provided as a file list.',
            'documents.min' => 'Please select at least one PDF file.',
            'documents.max' => "You can upload a maximum of {$maxFiles} PDF files per batch.",
            'documents.*.required' => 'A valid file is required for each upload item.',
            'documents.*.file' => 'The selected file is invalid.',
            'documents.*.mimes' => 'Only PDF files are supported (:values). Please upload valid .pdf documents.',
            'documents.*.max' => "PDF file size must not exceed {$maxMb} MB.",
        ];
    }
}
