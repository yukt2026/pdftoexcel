<?php

namespace App\Exports;

use App\Exports\Sheets\InvoiceItemsSheet;
use App\Exports\Sheets\InvoiceSummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InvoiceWorkbookExport implements WithMultipleSheets
{
    protected mixed $invoices;

    public function __construct(mixed $invoices)
    {
        $this->invoices = $invoices;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new InvoiceItemsSheet($this->invoices),
            new InvoiceSummarySheet($this->invoices),
        ];
    }
}
