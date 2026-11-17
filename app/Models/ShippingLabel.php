<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShippingLabel extends Model
{
    use HasFactory;

    public const FORMAT_FLIPKART_EKART = 'flipkart_ekart';
    public const FORMAT_DELHIVERY = 'delhivery';
    public const FORMAT_STANDARD_COURIER = 'standard_courier';

    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_DISCREPANCY = 'discrepancy';

    protected $attributes = [
        'is_manually_corrected' => false,
        'label_format' => self::FORMAT_STANDARD_COURIER,
        'status' => self::STATUS_EXTRACTED,
        'extraction_method' => 'deterministic',
        'confidence_score' => 90.00,
        'page_number' => 1,
    ];

    protected $fillable = [
        'user_id',
        'document_id',
        'document_page_id',
        'invoice_id',
        'awb_number',
        'original_awb_number',
        'order_number',
        'buyer_name',
        'shipping_address',
        'state',
        'pincode',
        'courier_name',
        'marketplace_name',
        'label_format',
        'page_number',
        'extraction_method',
        'confidence_score',
        'field_confidence_scores',
        'is_manually_corrected',
        'correction_notes',
        'barcode_data',
        'qr_data',
        'raw_text',
        'raw_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'confidence_score' => 'decimal:2',
            'field_confidence_scores' => 'array',
            'raw_data' => 'array',
            'is_manually_corrected' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentPage(): BelongsTo
    {
        return $this->belongsTo(DocumentPage::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function matchingRecord(): HasOne
    {
        return $this->hasOne(MatchingRecord::class, 'shipping_id');
    }

    public function shippingMatch(): HasOne
    {
        return $this->hasOne(InvoiceShippingMatch::class);
    }

    /**
     * Virtual accessor for shipping ID (AWB Number).
     */
    public function getShippingIdAttribute(): ?string
    {
        return $this->awb_number;
    }

    /**
     * Manually correct the extracted shipping AWB identifier.
     * Preserves the original tracking number for audit trail.
     */
    public function correctAwb(string $newAwb, ?string $notes = null): self
    {
        $oldAwb = $this->awb_number;
        $cleanAwb = trim($newAwb);

        if (empty($this->original_awb_number)) {
            $this->original_awb_number = $oldAwb;
        }

        $this->awb_number = $cleanAwb;
        $this->is_manually_corrected = true;
        if ($notes) {
            $this->correction_notes = $notes;
        }
        $this->save();

        ActivityLog::record(
            'shipping_label_awb_corrected',
            "Manually corrected AWB from '{$oldAwb}' to '{$cleanAwb}' for Order '{$this->order_number}'.",
            $this,
            [
                'original_awb' => $this->original_awb_number,
                'previous_awb' => $oldAwb,
                'new_awb' => $cleanAwb,
                'notes' => $notes,
            ]
        );

        return $this;
    }

    // Scopes
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCourier(Builder $query, string $courier): Builder
    {
        return $query->where('courier_name', 'like', "%{$courier}%");
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('awb_number', 'like', "%{$term}%")
              ->orWhere('original_awb_number', 'like', "%{$term}%")
              ->orWhere('order_number', 'like', "%{$term}%")
              ->orWhere('buyer_name', 'like', "%{$term}%")
              ->orWhere('pincode', 'like', "%{$term}%")
              ->orWhere('courier_name', 'like', "%{$term}%");
        });
    }
}
