<?php

namespace App\Listeners;

use App\Events\InvoiceProcessedEvent;
use App\Models\ActivityLog;

class LogInvoiceActivityListener
{
    public function handle(InvoiceProcessedEvent $event): void
    {
        ActivityLog::record(
            'invoice_processed_event',
            "Invoice #{$event->invoice->invoice_number} processed event handled.",
            $event->invoice
        );
    }
}
