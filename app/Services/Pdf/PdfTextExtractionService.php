<?php

namespace App\Services\Pdf;

use Exception;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfTextExtractionService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Inspect if a PDF is encrypted or password-protected.
     */
    public function isPasswordProtected(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        // Read first 64KB for /Encrypt dictionary
        $contents = fread($handle, 65536);
        fclose($handle);

        if (preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/i', $contents) || preg_match('/\/Encrypt\b/i', $contents)) {
            return true;
        }

        return false;
    }

    /**
     * Inspect if a PDF file is structurally corrupted.
     */
    public function isCorrupted(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return true;
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return true;
        }

        $header = fread($handle, 1024);
        fseek($handle, max(0, filesize($filePath) - 1024));
        $trailer = fread($handle, 1024);
        fclose($handle);

        $hasPdfHeader = str_contains($header, '%PDF-');
        $hasEof = str_contains($trailer, '%%EOF') || str_contains($trailer, 'startxref');

        return !$hasPdfHeader;
    }

    /**
     * Extract text and metadata from PDF with multi-page and scanned detection.
     *
     * @param string $filePath
     * @return array{
     *   success: bool,
     *   raw_text: string,
     *   page_count: int,
     *   pages: array<int, array{page_number: int, text: string, char_count: int, is_scanned: bool, status: string, error: ?string}>,
     *   is_scanned: bool,
     *   has_password: bool,
     *   is_corrupted: bool,
     *   error_message: ?string,
     *   metadata: array
     * }
     */
    public function extractText(string $filePath): array
    {
        @ini_set('memory_limit', '512M');

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'raw_text' => '',
                'page_count' => 0,
                'pages' => [],
                'is_scanned' => false,
                'has_password' => false,
                'is_corrupted' => true,
                'error_message' => "PDF file does not exist on disk: {$filePath}",
                'metadata' => [],
            ];
        }

        // 1. Check for Password Protection
        if ($this->isPasswordProtected($filePath)) {
            return [
                'success' => false,
                'raw_text' => '',
                'page_count' => 0,
                'pages' => [],
                'is_scanned' => false,
                'has_password' => true,
                'is_corrupted' => false,
                'error_message' => 'The PDF is encrypted or password-protected. Please upload an unprotected file or remove the password.',
                'metadata' => ['has_password' => true],
            ];
        }

        // 2. Check for Corruption
        if ($this->isCorrupted($filePath)) {
            return [
                'success' => false,
                'raw_text' => '',
                'page_count' => 0,
                'pages' => [],
                'is_scanned' => false,
                'has_password' => false,
                'is_corrupted' => true,
                'error_message' => 'The PDF file is corrupted or does not conform to the standard PDF specification (%PDF-).',
                'metadata' => ['is_corrupted' => true],
            ];
        }

        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            $extractedPages = [];
            $fullRawText = '';
            $scannedPagesCount = 0;

            // If parser detected 0 text pages, inspect raw structure for physical page objects
            if ($pageCount === 0) {
                $content = @file_get_contents($filePath);
                $fallbackCount = 1;
                if ($content && preg_match_all("/\/Type\s*\/Page\b/", $content, $m)) {
                    $fallbackCount = max(1, count($m[0]));
                }
                $pageCount = $fallbackCount;
                for ($p = 1; $p <= $pageCount; $p++) {
                    $extractedPages[] = [
                        'page_number' => $p,
                        'text' => '',
                        'char_count' => 0,
                        'is_scanned' => true,
                        'status' => 'empty',
                        'error' => 'Scanned page with no digital text stream.',
                    ];
                }
                $scannedPagesCount = $pageCount;
            } else {
                foreach ($pages as $index => $page) {
                    $pageNumber = $index + 1;
                    $pageText = '';
                    $pageError = null;
                    $pageStatus = 'completed';

                    try {
                        $pageText = $page->getText();
                        $pageText = $this->normalizeText($pageText);
                    } catch (Throwable $pe) {
                        $pageError = "Failed to extract text from page {$pageNumber}: " . $pe->getMessage();
                        $pageStatus = 'failed';
                        Log::warning($pageError, ['path' => $filePath, 'page' => $pageNumber]);
                    }

                    $charCount = mb_strlen(trim($pageText));
                    $isPageScanned = false;

                    // Scanned page heuristic: low character count or very few alphanumeric chars
                    if ($pageStatus === 'completed') {
                        if ($charCount < 50) {
                            $isPageScanned = true;
                            $scannedPagesCount++;
                            if ($charCount === 0) {
                                $pageStatus = 'empty';
                            }
                        } else {
                            // Check alphanumeric density
                            $alnumCount = preg_match_all('/[a-zA-Z0-9]/', $pageText);
                            if ($alnumCount < ($charCount * 0.25)) {
                                $isPageScanned = true;
                                $scannedPagesCount++;
                            }
                        }
                    } else {
                        // Unreadable page
                        $isPageScanned = true;
                        $scannedPagesCount++;
                    }

                    $extractedPages[] = [
                        'page_number' => $pageNumber,
                        'text' => $pageText,
                        'char_count' => $charCount,
                        'is_scanned' => $isPageScanned,
                        'status' => $pageStatus,
                        'error' => $pageError,
                    ];

                    $fullRawText .= "\n--- Page {$pageNumber} ---\n" . $pageText;
                }
            }

            $fullRawText = trim($fullRawText);
            $isOverallScanned = ($pageCount > 0 && ($scannedPagesCount / $pageCount) >= 0.5);

            // Detect Indian invoice / logistics indicators
            $detectedMarkers = $this->detectIndianInvoiceMarkers($fullRawText);

            $details = [];
            try {
                $details = $pdf->getDetails();
            } catch (Throwable) {
                // Ignore metadata error
            }

            return [
                'success' => true,
                'raw_text' => $fullRawText,
                'page_count' => $pageCount,
                'pages' => $extractedPages,
                'is_scanned' => $isOverallScanned,
                'has_password' => false,
                'is_corrupted' => false,
                'error_message' => null,
                'metadata' => array_merge(is_array($details) ? $details : [], [
                    'detected_markers' => $detectedMarkers,
                    'scanned_pages' => $scannedPagesCount,
                ]),
            ];
        } catch (Throwable $e) {
            Log::warning('PDF text parser failed to decode streams, treating as scanned document: ' . $e->getMessage(), ['file' => $filePath]);

            // Fallback for purely scanned/raster PDFs where parser cannot decode font streams
            $content = @file_get_contents($filePath);
            $pageCount = 1;
            if ($content && preg_match_all("/\/Type\s*\/Page\b/", $content, $m)) {
                $pageCount = max(1, count($m[0]));
            }

            $extractedPages = [];
            for ($p = 1; $p <= $pageCount; $p++) {
                $extractedPages[] = [
                    'page_number' => $p,
                    'text' => '',
                    'char_count' => 0,
                    'is_scanned' => true,
                    'status' => 'empty',
                    'error' => 'Selectable text unavailable. Page flagged for OCR.',
                ];
            }

            return [
                'success' => true,
                'raw_text' => '',
                'page_count' => $pageCount,
                'pages' => $extractedPages,
                'is_scanned' => true,
                'has_password' => false,
                'is_corrupted' => false,
                'error_message' => null,
                'metadata' => [
                    'scanned_pages' => $pageCount,
                    'parser_fallback' => true,
                    'parser_notice' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Normalize Indian and English typography, currency symbols, and spaces.
     */
    protected function normalizeText(string $text): string
    {
        // Replace Indian Rupee symbol variants
        $text = str_replace(["\u{20B9}", '`', 'â‚¹'], '₹', $text);

        // Normalize non-breaking spaces and tabs
        $text = str_replace(["\xC2\xA0", "\r\n", "\r"], [" ", "\n", "\n"], $text);

        // Remove illegal control characters while preserving newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return $text;
    }

    /**
     * Detect presence of Indian invoice patterns and logistics identifiers.
     */
    protected function detectIndianInvoiceMarkers(string $text): array
    {
        $markers = [];

        // GSTIN (15-digit alphanumeric Indian GST identifier)
        if (preg_match_all('/\b([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1})\b/', $text, $gstins)) {
            $markers['gstins'] = array_unique($gstins[1]);
        }

        // Indian PAN Card (10-digit alphanumeric)
        if (preg_match_all('/\b([A-Z]{5}[0-9]{4}[A-Z]{1})\b/', $text, $pans)) {
            $markers['pans'] = array_unique($pans[1]);
        }

        // Order IDs (Flipkart OD..., Amazon 40x-..., etc.)
        if (preg_match_all('/\b(OD[0-9]{18}|[0-9]{3}-[0-9]{7}-[0-9]{7})\b/i', $text, $orderIds)) {
            $markers['order_ids'] = array_unique($orderIds[1]);
        }

        // Logistics Tracking / AWB (E-Kart FMPC..., Delhivery 12-14 digits, FedEx, etc.)
        if (preg_match_all('/\b(FMPC[0-9]{10}|227[0-9]{10}|[0-9]{12,14}|TRK-[A-Z0-9]{8,14})\b/i', $text, $awbs)) {
            $markers['tracking_numbers'] = array_unique($awbs[1]);
        }

        // Carrier names
        $carriers = [];
        if (stripos($text, 'E-Kart') !== false || stripos($text, 'Ekart') !== false) $carriers[] = 'E-Kart Logistics';
        if (stripos($text, 'Delhivery') !== false) $carriers[] = 'Delhivery';
        if (stripos($text, 'FedEx') !== false) $carriers[] = 'FedEx';
        if (stripos($text, 'Blue Dart') !== false || stripos($text, 'Bluedart') !== false) $carriers[] = 'Blue Dart';
        if (stripos($text, 'DTDC') !== false) $carriers[] = 'DTDC';
        if (stripos($text, 'Xpressbees') !== false) $carriers[] = 'Xpressbees';

        if (!empty($carriers)) {
            $markers['detected_carriers'] = array_unique($carriers);
        }

        // Invoice Header Markers
        $markers['has_tax_invoice'] = (stripos($text, 'Tax Invoice') !== false || stripos($text, 'TAX INVOICE') !== false);
        $markers['has_shipping_label'] = (stripos($text, 'E-Kart Logistics') !== false || stripos($text, 'Delhivery') !== false || stripos($text, 'Pickup') !== false || stripos($text, 'Destination Code') !== false);

        return $markers;
    }
}
