<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected mixed $invoices;

    public function __construct(mixed $invoices = null)
    {
        $this->invoices = $invoices ?: Invoice::with(['vendor', 'customer', 'items'])->latest()->get();
    }

    public function collection()
    {
        return $this->invoices;
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Date',
            'Due Date',
            'PO Number',
            'Vendor / Supplier',
            'Customer / Buyer',
            'Currency',
            'Subtotal',
            'Tax Amount',
            'Discount',
            'Shipping',
            'Grand Total',
            'Payment Status',
            'Extraction Method',
            'AI Confidence %',
            'Matching Status',
        ];
    }

    /**
     * @param Invoice $invoice
     */
    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->invoice_date?->format('Y-m-d') ?? '',
            $invoice->due_date?->format('Y-m-d') ?? '',
            $invoice->po_number ?? 'N/A',
            $invoice->vendor?->name ?? 'N/A',
            $invoice->customer?->name ?? 'N/A',
            $invoice->currency,
            (float) $invoice->subtotal,
            (float) $invoice->tax_amount,
            (float) $invoice->discount_amount,
            (float) $invoice->shipping_amount,
            (float) $invoice->total_amount,
            strtoupper($invoice->payment_status),
            strtoupper($invoice->extraction_method),
            $invoice->ai_confidence_score ? $invoice->ai_confidence_score . '%' : 'N/A',
            strtoupper($invoice->matching_status),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E293B'], // Dark slate header
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
