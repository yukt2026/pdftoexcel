<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Invoice to Excel Configuration
    |--------------------------------------------------------------------------
    */

    'supported_formats' => [
        'pdf' => 'application/pdf',
    ],

    'max_upload_size_kb' => 51200, // 50 MB
    'max_batch_files' => 25,

    'matching' => [
        'default_tolerance_percent' => 2.0,
        'rules' => [
            'verify_po_number' => true,
            'verify_line_totals' => true,
            'verify_shipping_charges' => true,
        ],
    ],

    'export' => [
        'default_format' => 'xlsx',
        'formats' => ['xlsx', 'csv'],
        'headers_style' => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']],
        ],
    ],
];
