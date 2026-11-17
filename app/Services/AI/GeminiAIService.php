<?php

namespace App\Services\AI;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAIService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = Setting::get('gemini_api_key') 
            ?: config('services.gemini.api_key') 
            ?: env('GEMINI_API_KEY');

        $this->model = Setting::get('gemini_model') 
            ?: config('services.gemini.model') 
            ?: env('GEMINI_MODEL', 'gemini-1.5-flash');
    }

    /**
     * Check if Gemini API is configured with an active key.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Extract structured invoice data from parsed raw document text.
     *
     * @param string $extractedText
     * @return array
     */
    public function extractInvoiceFromText(string $extractedText): array
    {
        if (!$this->isConfigured()) {
            throw new Exception("Google Gemini API Key is not configured. Please add your API key in Settings.");
        }

        $systemPrompt = <<<PROMPT
You are an expert Document and Invoice Intelligence AI.
Analyze the following invoice text extracted from an invoice file and return ONLY a valid JSON object matching the requested schema.
Ensure high precision. Convert all numerical totals to decimal numbers (e.g. 1250.00). Dates should be formatted as YYYY-MM-DD.
If a field is not present or unknown, use null.

JSON Schema format:
{
  "invoice_number": "string or null",
  "invoice_date": "YYYY-MM-DD or null",
  "due_date": "YYYY-MM-DD or null",
  "po_number": "string or null",
  "currency": "3-letter currency code or default INR",
  "vendor": {
    "name": "string",
    "tax_number": "string or null",
    "email": "string or null",
    "phone": "string or null",
    "address": "string or null",
    "city": "string or null",
    "state": "string or null",
    "country": "string or null",
    "postal_code": "string or null"
  },
  "customer": {
    "name": "string or null",
    "tax_number": "string or null",
    "email": "string or null",
    "phone": "string or null",
    "address": "string or null",
    "city": "string or null",
    "state": "string or null",
    "country": "string or null",
    "postal_code": "string or null"
  },
  "items": [
    {
      "item_code": "string or null",
      "description": "string",
      "quantity": 1.0,
      "unit": "string or null",
      "unit_price": 0.0,
      "discount": 0.0,
      "tax_rate": 0.0,
      "tax_amount": 0.0,
      "total_price": 0.0,
      "confidence_score": 95.0
    }
  ],
  "totals": {
    "subtotal": 0.0,
    "tax_amount": 0.0,
    "discount_amount": 0.0,
    "shipping_amount": 0.0,
    "total_amount": 0.0,
    "amount_paid": 0.0,
    "balance_due": 0.0
  },
  "payment_terms": "string or null",
  "overall_confidence_score": 95.0,
  "notes": "string or null"
}
PROMPT;

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt],
                            ['text' => "INVOICE DOCUMENT TEXT:\n\n" . $extractedText]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: ' . $response->body());
                throw new Exception("Gemini API request failed: " . ($response->json('error.message') ?? $response->status()));
            }

            $result = $response->json();
            $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try trimming markdown fences if any
                $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
                $parsed = json_decode($clean, true);
            }

            return is_array($parsed) ? $parsed : [];
        } catch (Exception $e) {
            Log::error('Gemini API Exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
