<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'customer_id',
        'document_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'po_number',
        'order_number',
        'shipping_id',
        'buyer_name',
        'buyer_address',
        'buyer_state',
        'place_of_supply',
        'currency',
        'subtotal',
        'tax_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'discount_amount',
        'shipping_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_terms',
        'payment_status',
        'processing_status',
        'is_manually_edited',
        'matching_status',
        'extraction_method',
        'ai_confidence_score',
        'notes',
        'raw_extracted_text',
        'ai_raw_response',
        'meta_data',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'ai_confidence_score' => 'decimal:2',
            'is_manually_edited' => 'boolean',
            'ai_raw_response' => 'array',
            'meta_data' => 'array',
        ];
    }

    protected $attributes = [
        'currency' => 'INR',
    ];

    public function getCurrencySymbolAttribute(): string
    {
        return '₹';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function extractionResult(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InvoiceExtractionResult::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function shippingDocuments(): HasMany
    {
        return $this->hasMany(ShippingDocument::class);
    }

    public function shippingLabels(): HasMany
    {
        return $this->hasMany(ShippingLabel::class);
    }

    public function shippingMatch(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InvoiceShippingMatch::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(InvoiceCorrection::class)->latest();
    }

    public function matchingRecords(): HasMany
    {
        return $this->hasMany(MatchingRecord::class);
    }

    /**
     * Resolve the active shipping identifier (direct invoice column or matched label AWB).
     */
    public function getResolvedShippingIdAttribute(): ?string
    {
        if (!empty($this->shipping_id)) {
            return $this->shipping_id;
        }

        return $this->shippingMatch?->shippingLabel?->shipping_id 
            ?: ($this->shippingMatch?->shippingLabel?->awb_number 
            ?: $this->shippingMatch?->awb_number);
    }

    /**
     * Compute comprehensive validation checks and audit discrepancies.
     */
    public function getValidationIssuesAttribute(): array
    {
        $missingFields = [];
        if (empty(trim((string) $this->invoice_number))) $missingFields[] = 'Invoice Number';
        if (empty($this->invoice_date)) $missingFields[] = 'Invoice Date';
        if (empty(trim((string) ($this->buyer_name ?? $this->customer?->name)))) $missingFields[] = 'Buyer Name';
        if (empty(trim((string) ($this->buyer_state ?? $this->place_of_supply)))) $missingFields[] = 'State / Place of Supply';
        if (empty(trim((string) $this->order_number)) && empty(trim((string) $this->po_number))) $missingFields[] = 'Order / PO Number';
        if ((float) $this->total_amount <= 0) $missingFields[] = 'Total Amount';

        // Low confidence check
        $isLowConfidence = ($this->ai_confidence_score !== null && (float) $this->ai_confidence_score < 85.0);

        // Shipping matching check
        $isUnmatchedShipping = ($this->matching_status !== 'matched' && empty($this->shipping_id) && empty($this->shippingMatch?->shipping_label_id));

        // Duplicate invoice detection
        $isPossibleDuplicate = false;
        $duplicateMatches = collect();
        if (!empty($this->invoice_number)) {
            $duplicateMatches = static::where('invoice_number', $this->invoice_number)
                ->where('id', '!=', $this->id)
                ->get(['id', 'invoice_number', 'user_id', 'created_at']);
            $isPossibleDuplicate = $duplicateMatches->isNotEmpty();
        }

        // Tax & Mathematical calculation discrepancies
        $itemsSum = (float) $this->items->sum('total_price');
        $itemDiscrepancy = round(abs($itemsSum - (float) $this->total_amount), 2);

        $taxable = (float) ($this->subtotal ?: $this->items->sum('taxable_value'));
        $taxes = (float) ($this->tax_amount ?: ($this->cgst_amount + $this->sgst_amount + $this->igst_amount));
        $shipping = (float) $this->shipping_amount;
        $discount = (float) $this->discount_amount;
        $calculatedGrandTotal = round($taxable + $taxes + $shipping - $discount, 2);
        $taxFormulaDiscrepancy = round(abs($calculatedGrandTotal - (float) $this->total_amount), 2);

        $hasTaxDiscrepancy = ($itemDiscrepancy > 1.00 || $taxFormulaDiscrepancy > 1.00);

        return [
            'has_issues' => !empty($missingFields) || $isLowConfidence || $isUnmatchedShipping || $isPossibleDuplicate || $hasTaxDiscrepancy,
            'missing_fields' => $missingFields,
            'is_low_confidence' => $isLowConfidence,
            'confidence_score' => (float) ($this->ai_confidence_score ?? 90),
            'is_unmatched_shipping' => $isUnmatchedShipping,
            'is_possible_duplicate' => $isPossibleDuplicate,
            'duplicate_matches' => $duplicateMatches,
            'has_tax_discrepancy' => $hasTaxDiscrepancy,
            'items_sum' => $itemsSum,
            'item_discrepancy' => $itemDiscrepancy,
            'calculated_grand_total' => $calculatedGrandTotal,
            'tax_formula_discrepancy' => $taxFormulaDiscrepancy,
        ];
    }

    /**
     * Retrieve original AI extracted data for audit comparison.
     */
    public function getOriginalExtractedDataAttribute(): array
    {
        if ($this->extractionResult && !empty($this->extractionResult->extracted_data)) {
            return $this->extractionResult->extracted_data;
        }

        if (!empty($this->meta_data['original_extracted_data'])) {
            return $this->meta_data['original_extracted_data'];
        }

        if (!empty($this->ai_raw_response)) {
            return $this->ai_raw_response;
        }

        return [
            'invoice' => [
                'invoice_number' => $this->invoice_number,
                'invoice_date' => $this->invoice_date?->format('Y-m-d'),
                'po_number' => $this->po_number,
                'order_number' => $this->order_number,
                'buyer_name' => $this->buyer_name,
                'buyer_address' => $this->buyer_address,
                'buyer_state' => $this->buyer_state,
                'place_of_supply' => $this->place_of_supply,
                'total_amount' => (float) $this->total_amount,
            ],
            'products' => $this->items->map(function ($it) {
                return [
                    'item_name' => $it->description,
                    'item_rate' => (float) $it->item_rate,
                    'taxable_value' => (float) $it->taxable_value,
                    'cgst' => (float) $it->cgst,
                    'sgst' => (float) $it->sgst,
                    'igst' => (float) $it->igst,
                    'total' => (float) $it->total_price,
                ];
            })->toArray(),
        ];
    }

    // Scopes
    public function scopeProcessingStatus(Builder $query, string $status): Builder
    {
        return $query->where('processing_status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('processing_status', ['pending', 'processing']);
    }

    public function scopeExtracted(Builder $query): Builder
    {
        return $query->whereIn('processing_status', ['extracted', 'review_needed', 'validated', 'exported']);
    }
}
