<?php

namespace App\Services\Pdf;

use Exception;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class PdfParserService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Get the total number of pages in a PDF.
     */
    public function getPageCount(string $filePath): int
    {
        if (!file_exists($filePath)) {
            return 1;
        }

        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $count = count($pages);
            if ($count > 0) {
                return $count;
            }
        } catch (Exception $e) {
            // Fallback to binary pattern scanning if parser encounters unsupported constructs
            try {
                $content = @file_get_contents($filePath);
                if ($content !== false) {
                    if (preg_match_all("/\/Type\s*\/Page\b/", $content, $matches)) {
                        $count = count($matches[0]);
                        if ($count > 0) {
                            return $count;
                        }
                    }
                    if (preg_match("/\/Count\s+(\d+)/", $content, $matches)) {
                        return max(1, (int) $matches[1]);
                    }
                }
            } catch (Exception) {
                // Ignore fallback error
            }
        }

        return 1;
    }

    /**
     * Parse text from a PDF file.
     *
     * @param string $filePath Absolute path to PDF file
     * @return array{text: string, page_count: int, is_scanned: bool, metadata: array}
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("PDF file not found at path: {$filePath}");
        }

        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);
            $extractedText = '';

            foreach ($pages as $index => $page) {
                $pageText = $page->getText();
                $extractedText .= "\n--- Page " . ($index + 1) . " ---\n" . $pageText;
            }

            $extractedText = trim($extractedText);
            // If average text length per page is very low, consider it scanned
            $isScanned = (strlen(trim($extractedText)) < ($pageCount * 50));
            $details = $pdf->getDetails();

            return [
                'text' => $extractedText,
                'page_count' => $pageCount > 0 ? $pageCount : 1,
                'is_scanned' => $isScanned,
                'metadata' => is_array($details) ? $details : [],
            ];
        } catch (Exception $e) {
            Log::error('PDF Parsing failed: ' . $e->getMessage(), ['path' => $filePath]);
            return [
                'text' => '',
                'page_count' => 1,
                'is_scanned' => true,
                'metadata' => ['error' => $e->getMessage()],
            ];
        }
    }
}
