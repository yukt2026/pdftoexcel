<?php

namespace App\Exports\Sheets;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoiceItemsSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    protected mixed $invoices;

    public function __construct(mixed $invoices)
    {
        $this->invoices = $invoices;
    }

    public function title(): string
    {
        return 'Invoice Items';
    }

    public function headings(): array
    {
        return [
            'Invoice Date',
            'Invoice Number',
            'PO Number',
            'Order Number',
            'Shipping ID',
            'Buyer Name',
            'Address',
            'State',
            'Item',
            'Item Rate',
            'Discount',
            'Taxable Value',
            'CGST',
            'SGST',
            'IGST',
            'Total',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->invoices as $invoice) {
            $invoiceDate = $invoice->invoice_date?->format('Y-m-d') ?: '';
            $invoiceNumber = $invoice->invoice_number ?: '';
            $poNumber = $invoice->po_number ?: '';
            $orderNumber = $invoice->order_number ?: '';
            $shippingId = $invoice->resolved_shipping_id ?: '';
            $buyerName = $invoice->buyer_name ?: ($invoice->customer?->name ?: '');
            $address = $invoice->buyer_address ?: ($invoice->customer?->address ?: '');
            $state = $invoice->buyer_state ?: ($invoice->place_of_supply ?: '');
            $invoiceGrandTotal = round((float) $invoice->total_amount, 2);

            $items = $invoice->items;
            $hasItems = $items->isNotEmpty();

            $isFirstRowForInvoice = true;
            $extraChargeHandled = false;

            if ($hasItems) {
                foreach ($items as $item) {
                    $itemDesc = $item->description ?: '';
                    $itemRate = round((float) ($item->item_rate ?: $item->unit_price), 2);
                    $discount = round((float) ($item->discount ?? 0), 2);
                    $taxable = round((float) ($item->taxable_value ?? 0), 2);
                    $cgst = round((float) ($item->cgst ?? 0), 2);
                    $sgst = round((float) ($item->sgst ?? 0), 2);
                    $igst = round((float) ($item->igst ?? 0), 2);

                    // Invoice Total should appear ONLY on the first product row for that invoice
                    $totalCell = $isFirstRowForInvoice ? $invoiceGrandTotal : '';

                    $rows[] = [
                        $invoiceDate,
                        $invoiceNumber,
                        $poNumber,
                        $orderNumber,
                        $shippingId,
                        $buyerName,
                        $address,
                        $state,
                        $itemDesc,
                        $itemRate,
                        $discount,
                        $taxable,
                        $cgst,
                        $sgst,
                        $igst,
                        $totalCell,
                    ];

                    $isFirstRowForInvoice = false;
                    if ($item->is_extra_charge || stripos($itemDesc, 'Shipping') !== false || stripos($itemDesc, 'Handling') !== false) {
                        $extraChargeHandled = true;
                    }
                }

                // If invoice has a distinct shipping amount not already captured as a line item
                if (!$extraChargeHandled && (float) $invoice->shipping_amount > 0) {
                    $shippingCharge = round((float) $invoice->shipping_amount, 2);
                    $rows[] = [
                        $invoiceDate,
                        $invoiceNumber,
                        $poNumber,
                        $orderNumber,
                        $shippingId,
                        $buyerName,
                        $address,
                        $state,
                        'Shipping and Handling Charges',
                        $shippingCharge,
                        0.00,
                        $shippingCharge,
                        0.00,
                        0.00,
                        0.00,
                        '', // Total appears only on first row
                    ];
                }
            } else {
                // If invoice has no line items, output 1 row with invoice-level data
                $subtotal = round((float) $invoice->subtotal, 2);
                $discount = round((float) $invoice->discount_amount, 2);
                $cgst = round((float) $invoice->cgst_amount, 2);
                $sgst = round((float) $invoice->sgst_amount, 2);
                $igst = round((float) $invoice->igst_amount, 2);

                $rows[] = [
                    $invoiceDate,
                    $invoiceNumber,
                    $poNumber,
                    $orderNumber,
                    $shippingId,
                    $buyerName,
                    $address,
                    $state,
                    '', // Blank item
                    $subtotal,
                    $discount,
                    $subtotal,
                    $cgst,
                    $sgst,
                    $igst,
                    $invoiceGrandTotal,
                ];
            }
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
            'M' => '#,##0.00',
            'N' => '#,##0.00',
            'O' => '#,##0.00',
            'P' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'], // Professional Emerald Green
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // 1. Freeze First Row
                $sheet->freezePane('A2');

                // 2. Enable AutoFilters
                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");

                // 3. Header row height
                $sheet->getRowDimension(1)->setRowHeight(28);

                // 4. Grid styling and Zebra striping
                if ($highestRow >= 2) {
                    // Border styling
                    $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);

                    // Alignments
                    $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E2:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J2:P{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Alternating row styling
                    for ($row = 2; $row <= $highestRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(20);
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F8FAFC'],
                                ],
                            ]);
                        }
                    }
                }
            },
        ];
    }
}
