<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default OCR Provider
    |--------------------------------------------------------------------------
    | Options: "google_vision", "tesseract", "auto", "mock"
    */
    'default_provider' => env('OCR_PROVIDER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Vision Configuration
    |--------------------------------------------------------------------------
    | Cloud Vision API endpoint and credentials for document OCR.
    | Document text detection is optimized for invoices, bills, and shipping labels.
    */
    'google_vision' => [
        'api_key' => env('GOOGLE_CLOUD_VISION_API_KEY', env('GOOGLE_VISION_API_KEY')),
        'endpoint' => 'https://vision.googleapis.com/v1/images:annotate',
        'features' => ['DOCUMENT_TEXT_DETECTION'],
        'language_hints' => ['en', 'hi'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR Configuration
    |--------------------------------------------------------------------------
    */
    'tesseract' => [
        'binary_path' => env('TESSERACT_PATH', null),
        'languages' => ['eng'],
    ],
];
