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

class InvoiceDetailExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(protected Invoice $invoice)
    {
        $this->invoice->load(['vendor', 'customer', 'items']);
    }

    public function collection()
    {
        return $this->invoice->items;
    }

    public function headings(): array
    {
        return [
            'Line #',
            'SKU / Code',
            'Description',
            'Quantity',
            'Unit',
            'Unit Price',
            'Discount',
            'Tax Rate %',
            'Tax Amount',
            'Total Price (' . $this->invoice->currency . ')',
        ];
    }

    public function map($item): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $item->item_code ?? '-',
            $item->description,
            (float) $item->quantity,
            $item->unit ?? 'unit',
            (float) $item->unit_price,
            (float) $item->discount,
            (float) $item->tax_rate,
            (float) $item->tax_amount,
            (float) $item->total_price,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'], // Royal blue
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
