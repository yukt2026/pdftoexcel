<?php

namespace App\Exports;

use App\Models\InvoiceItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoiceItemsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected mixed $items;

    public function __construct(mixed $items = null)
    {
        $this->items = $items ?: InvoiceItem::with('invoice.vendor')->latest()->get();
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Vendor',
            'Item Code / SKU',
            'Description',
            'Category',
            'Quantity',
            'Unit',
            'Unit Price',
            'Discount',
            'Tax Rate %',
            'Tax Amount',
            'Total Line Price',
            'Confidence %',
        ];
    }

    /**
     * @param InvoiceItem $item
     */
    public function map($item): array
    {
        return [
            $item->invoice?->invoice_number ?? 'N/A',
            $item->invoice?->vendor?->name ?? 'N/A',
            $item->item_code ?? 'N/A',
            $item->description,
            $item->category ?? 'General',
            (float) $item->quantity,
            $item->unit ?? 'pcs',
            (float) $item->unit_price,
            (float) $item->discount,
            (float) $item->tax_rate,
            (float) $item->tax_amount,
            (float) $item->total_price,
            $item->confidence_score ? $item->confidence_score . '%' : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D9488'], // Teal header
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
