<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceExtractionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'invoice_id',
        'user_id',
        'invoice_number',
        'invoice_date',
        'po_number',
        'order_number',
        'buyer_name',
        'buyer_address',
        'buyer_state',
        'place_of_supply',
        'total_taxable_value',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_amount',
        'extracted_data',
        'raw_text',
        'ai_raw_response',
        'ai_model',
        'overall_confidence',
        'field_confidence_scores',
        'status',
        'validation_errors',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'ai_raw_response' => 'array',
            'field_confidence_scores' => 'array',
            'validation_errors' => 'array',
            'total_taxable_value' => 'decimal:2',
            'total_cgst' => 'decimal:2',
            'total_sgst' => 'decimal:2',
            'total_igst' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'overall_confidence' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get extracted line items array.
     */
    public function getItemsAttribute(): array
    {
        return $this->extracted_data['items'] ?? [];
    }

    /**
     * Retrieve confidence score for a specific field.
     */
    public function getFieldConfidence(string $fieldName, float $default = 90.0): float
    {
        return (float) ($this->field_confidence_scores[$fieldName] ?? $this->overall_confidence ?? $default);
    }
}
