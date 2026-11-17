<?php

namespace App\Services\Pdf;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PdfPageRendererService
{
    protected string $tempDir;

    public function __construct()
    {
        $this->tempDir = storage_path('app/temp/rendered_pages');
        if (!File::exists($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }
    }

    /**
     * Check if a PDF page renderer engine is available on the server.
     */
    public function isAvailable(): bool
    {
        if (extension_loaded('imagick')) {
            return true;
        }

        // Check if pdftoppm or gs is in system PATH
        return $this->findBinary('pdftoppm') !== null || $this->findBinary('gs') !== null || $this->findBinary('gswin64c') !== null;
    }

    /**
     * Render a single 1-based page of a PDF to an image file.
     *
     * @param string $pdfPath
     * @param int $pageNumber (1-based)
     * @param int $dpi
     * @return string|null Path to rendered image or null if rendering failed/unavailable
     */
    public function renderPageToImage(string $pdfPath, int $pageNumber = 1, int $dpi = 200): ?string
    {
        if (!file_exists($pdfPath)) {
            Log::warning("Cannot render missing PDF: {$pdfPath}");
            return null;
        }

        $outputFilename = 'page_' . Str::uuid()->toString() . '.png';
        $outputPath = $this->tempDir . DIRECTORY_SEPARATOR . $outputFilename;

        // 1. Try Imagick PHP extension if loaded
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->setResolution($dpi, $dpi);
                $pageIndex = max(0, $pageNumber - 1);
                $imagick->readImage("{$pdfPath}[{$pageIndex}]");
                $imagick->setImageFormat('png');
                $imagick->setImageCompressionQuality(95);
                $imagick->writeImage($outputPath);
                $imagick->clear();
                $imagick->destroy();

                if (file_exists($outputPath)) {
                    return $outputPath;
                }
            } catch (Throwable $e) {
                Log::warning("Imagick page rendering failed: " . $e->getMessage());
            }
        }

        // 2. Try pdftoppm CLI
        $pdftoppm = $this->findBinary('pdftoppm');
        if ($pdftoppm) {
            try {
                $prefix = $this->tempDir . DIRECTORY_SEPARATOR . 'ppm_' . Str::uuid()->toString();
                $cmd = sprintf(
                    '"%s" -png -r %d -f %d -l %d "%s" "%s"',
                    $pdftoppm,
                    $dpi,
                    $pageNumber,
                    $pageNumber,
                    $pdfPath,
                    $prefix
                );
                @exec($cmd, $output, $returnCode);

                // pdftoppm appends -1.png or -01.png
                $candidates = glob($prefix . '*.png');
                if (!empty($candidates) && file_exists($candidates[0])) {
                    return $candidates[0];
                }
            } catch (Throwable $e) {
                Log::warning("pdftoppm rendering failed: " . $e->getMessage());
            }
        }

        // 3. Try Ghostscript CLI
        $gs = $this->findBinary('gswin64c') ?: $this->findBinary('gs');
        if ($gs) {
            try {
                $cmd = sprintf(
                    '"%s" -q -dNOPAUSE -dBATCH -sDEVICE=png16m -r%d -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s"',
                    $gs,
                    $dpi,
                    $pageNumber,
                    $pageNumber,
                    $outputPath,
                    $pdfPath
                );
                @exec($cmd, $output, $returnCode);

                if (file_exists($outputPath)) {
                    return $outputPath;
                }
            } catch (Throwable $e) {
                Log::warning("Ghostscript rendering failed: " . $e->getMessage());
            }
        }

        // 4. Fallback: extract embedded image streams if selectable text was unavailable
        $extractedStream = $this->extractEmbeddedImageStream($pdfPath, $pageNumber);
        if ($extractedStream && file_exists($extractedStream)) {
            return $extractedStream;
        }

        return null;
    }

    /**
     * Fallback heuristic: Extract raw image streams from PDF binary if rendered via scanner.
     */
    protected function extractEmbeddedImageStream(string $pdfPath, int $pageNumber): ?string
    {
        try {
            $content = @file_get_contents($pdfPath);
            if (!$content) {
                return null;
            }

            // Look for JPEG stream markers /DCTDecode inside the PDF
            $pos = strpos($content, '/DCTDecode');
            if ($pos !== false) {
                $streamStart = strpos($content, 'stream', $pos);
                if ($streamStart !== false) {
                    $streamStart += 6;
                    if (substr($content, $streamStart, 2) === "\r\n") $streamStart += 2;
                    elseif (substr($content, $streamStart, 1) === "\n") $streamStart += 1;

                    $streamEnd = strpos($content, 'endstream', $streamStart);
                    if ($streamEnd !== false) {
                        $jpegData = substr($content, $streamStart, $streamEnd - $streamStart);
                        $imagePath = $this->tempDir . DIRECTORY_SEPARATOR . 'stream_' . Str::uuid()->toString() . '.jpg';
                        file_put_contents($imagePath, $jpegData);
                        if (file_exists($imagePath) && filesize($imagePath) > 500) {
                            return $imagePath;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Ignore fallback stream extraction error
        }

        return null;
    }

    /**
     * Clean up generated temporary images.
     */
    public function cleanup(array|string $paths): void
    {
        $paths = is_array($paths) ? $paths : [$paths];
        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Locate executable binary on PATH.
     */
    protected function findBinary(string $binary): ?string
    {
        $whereCmd = (PHP_OS_FAMILY === 'Windows') ? "where {$binary} 2>nul" : "which {$binary} 2>/dev/null";
        $path = @exec($whereCmd);
        return (!empty($path) && file_exists($path)) ? $path : null;
    }
}
