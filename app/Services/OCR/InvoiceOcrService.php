<?php

namespace App\Services\OCR;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

class InvoiceOcrService
{
    protected ?string $configuredProvider = null;

    public function __construct(?string $provider = null)
    {
        $this->configuredProvider = $provider;
    }

    /**
     * Set active OCR provider on the fly.
     */
    public function setProvider(string $provider): self
    {
        $this->configuredProvider = $provider;
        return $this;
    }

    /**
     * Resolve active OCR provider name.
     */
    public function getActiveProvider(): string
    {
        if ($this->configuredProvider) {
            return $this->configuredProvider;
        }

        // Check database setting first
        $dbProvider = Setting::get('ocr_provider');
        if ($dbProvider && in_array($dbProvider, ['google_vision', 'tesseract', 'mock', 'none'])) {
            return $dbProvider;
        }

        $configProvider = config('ocr.default_provider', 'auto');
        if ($configProvider === 'auto') {
            if ($this->getGoogleVisionKey()) {
                return 'google_vision';
            }
            if ($this->isTesseractAvailable()) {
                return 'tesseract';
            }
            return 'mock';
        }

        return $configProvider;
    }

    /**
     * Retrieve Google Cloud Vision API key from database setting or env.
     */
    public function getGoogleVisionKey(): ?string
    {
        return Setting::get('google_vision_api_key')
            ?: config('ocr.google_vision.api_key')
            ?: env('GOOGLE_CLOUD_VISION_API_KEY')
            ?: env('GOOGLE_VISION_API_KEY');
    }

    /**
     * Check if local Tesseract OCR binary is installed and executable.
     */
    public function isTesseractAvailable(): bool
    {
        $customPath = config('ocr.tesseract.binary_path');
        if ($customPath && file_exists($customPath)) {
            return true;
        }

        $whereCmd = (PHP_OS_FAMILY === 'Windows') ? 'where tesseract 2>nul' : 'which tesseract 2>/dev/null';
        $out = @exec($whereCmd);
        return !empty($out) && file_exists($out);
    }

    /**
     * Check if any valid OCR provider is configured.
     */
    public function isAvailable(): bool
    {
        $provider = $this->getActiveProvider();
        if ($provider === 'google_vision') {
            return !empty($this->getGoogleVisionKey());
        }
        if ($provider === 'tesseract') {
            return $this->isTesseractAvailable();
        }
        return true;
    }

    /**
     * Perform OCR text extraction on an image file.
     *
     * @param string $imagePath
     * @return array{success: bool, text: string, confidence: float, provider: string, error: ?string}
     */
    public function extractText(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'text' => '',
                'confidence' => 0.0,
                'provider' => 'none',
                'error' => "Image file not found: {$imagePath}",
            ];
        }

        $provider = $this->getActiveProvider();

        switch ($provider) {
            case 'google_vision':
                return $this->extractWithGoogleVision($imagePath);

            case 'tesseract':
                return $this->extractWithTesseract($imagePath);

            case 'mock':
            default:
                return $this->extractWithMock($imagePath);
        }
    }

    /**
     * Google Cloud Vision Document Text Detection (Dense table and invoice optimized).
     */
    protected function extractWithGoogleVision(string $imagePath): array
    {
        $apiKey = $this->getGoogleVisionKey();

        if (empty($apiKey)) {
            Log::warning('Google Cloud Vision requested but API key is missing.');
            return [
                'success' => false,
                'text' => '',
                'confidence' => 0.0,
                'provider' => 'google_vision',
                'error' => 'Google Cloud Vision API key is not configured in settings or environment.',
            ];
        }

        $endpoint = config('ocr.google_vision.endpoint', 'https://vision.googleapis.com/v1/images:annotate');
        $endpointWithKey = "{$endpoint}?key={$apiKey}";

        try {
            $imageData = base64_encode(file_get_contents($imagePath));

            $response = Http::timeout(30)->post($endpointWithKey, [
                'requests' => [
                    [
                        'image' => [
                            'content' => $imageData,
                        ],
                        'features' => [
                            [
                                'type' => 'DOCUMENT_TEXT_DETECTION',
                                'maxResults' => 1,
                            ],
                        ],
                        'imageContext' => [
                            'languageHints' => config('ocr.google_vision.language_hints', ['en', 'hi']),
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $firstResp = $data['responses'][0] ?? [];

                if (isset($firstResp['error'])) {
                    throw new Exception($firstResp['error']['message'] ?? 'Cloud Vision API returned an error.');
                }

                $fullText = $firstResp['fullTextAnnotation']['text']
                    ?? $firstResp['textAnnotations'][0]['description']
                    ?? '';

                // Calculate average page confidence score if available
                $confidence = 90.0;
                if (!empty($firstResp['fullTextAnnotation']['pages'][0]['confidence'])) {
                    $confidence = round($firstResp['fullTextAnnotation']['pages'][0]['confidence'] * 100, 2);
                }

                return [
                    'success' => true,
                    'text' => trim($fullText),
                    'confidence' => $confidence,
                    'provider' => 'google_vision',
                    'error' => null,
                ];
            }

            $errorMsg = 'Google Cloud Vision API HTTP ' . $response->status() . ': ' . $response->body();
            Log::error($errorMsg);

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0.0,
                'provider' => 'google_vision',
                'error' => $errorMsg,
            ];
        } catch (Throwable $e) {
            Log::error('Google Cloud Vision call failed: ' . $e->getMessage());

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0.0,
                'provider' => 'google_vision',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Local Tesseract OCR extraction.
     */
    protected function extractWithTesseract(string $imagePath): array
    {
        try {
            $ocr = new TesseractOCR($imagePath);

            $customPath = config('ocr.tesseract.binary_path');
            if ($customPath) {
                $ocr->executable($customPath);
            }

            $ocr->lang(...config('ocr.tesseract.languages', ['eng']));

            $text = $ocr->run();

            return [
                'success' => true,
                'text' => trim($text),
                'confidence' => 80.0,
                'provider' => 'tesseract',
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Tesseract OCR failed: ' . $e->getMessage());

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0.0,
                'provider' => 'tesseract',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mock OCR provider for offline test environments.
     */
    protected function extractWithMock(string $imagePath): array
    {
        return [
            'success' => true,
            'text' => "[OCR Mock: Document rendered from image " . basename($imagePath) . "]\nInvoice No: INV-MOCK-001\nTotal: 1250.00",
            'confidence' => 75.0,
            'provider' => 'mock',
            'error' => null,
        ];
    }
}
