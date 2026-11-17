<?php

namespace App\Services\OCR;

use Exception;
use Illuminate\Support\Facades\Log;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    protected ?string $tesseractPath;

    public function __construct()
    {
        // Try common windows tesseract locations or custom path from config
        $this->tesseractPath = config('services.tesseract.path', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
    }

    /**
     * Check if local Tesseract OCR binary is available.
     */
    public function isAvailable(): bool
    {
        return !empty($this->tesseractPath) && file_exists($this->tesseractPath);
    }

    /**
     * Extract text from image file using OCR.
     */
    public function extractTextFromImage(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            Log::warning("OCR file does not exist: {$imagePath}");
            return '';
        }

        try {
            $ocr = new TesseractOCR($imagePath);

            if ($this->isAvailable()) {
                $ocr->executable($this->tesseractPath);
            }

            return trim($ocr->run());
        } catch (Exception $e) {
            Log::warning("OCR processing warning or unavailable: " . $e->getMessage());
            return '';
        }
    }
}
