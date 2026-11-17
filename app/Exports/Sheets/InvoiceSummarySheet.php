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

class InvoiceSummarySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    protected mixed $invoices;

    public function __construct(mixed $invoices)
    {
        $this->invoices = $invoices;
    }

    public function title(): string
    {
        return 'Invoice Summary';
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
            'State',
            'Total Taxable Value',
            'Total CGST',
            'Total SGST',
            'Total IGST',
            'Invoice Total',
            'Matching Status',
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
            $state = $invoice->buyer_state ?: ($invoice->place_of_supply ?: '');

            $taxableValue = round((float) ($invoice->subtotal ?: $invoice->items->sum('taxable_value')), 2);
            $totalCgst = round((float) $invoice->cgst_amount, 2);
            $totalSgst = round((float) $invoice->sgst_amount, 2);
            $totalIgst = round((float) $invoice->igst_amount, 2);
            $invoiceTotal = round((float) $invoice->total_amount, 2);
            $matchingStatus = ucfirst($invoice->matching_status ?: 'unmatched');

            $rows[] = [
                $invoiceDate,
                $invoiceNumber,
                $poNumber,
                $orderNumber,
                $shippingId,
                $buyerName,
                $state,
                $taxableValue,
                $totalCgst,
                $totalSgst,
                $totalIgst,
                $invoiceTotal,
                $matchingStatus,
            ];
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
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
                    $sheet->getStyle("G2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H2:L{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("M2:M{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
