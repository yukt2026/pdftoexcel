<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceShippingMatch extends Model
{
    use HasFactory;

    // Matching Statuses
    public const STATUS_MATCHED = 'matched';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_DUPLICATE = 'duplicate';

    // Matching Methods
    public const METHOD_EXACT_ORDER = 'exact_order_number';
    public const METHOD_EXACT_PO = 'exact_po_number';
    public const METHOD_NORMALIZED_ORDER = 'normalized_order_number';
    public const METHOD_SUFFIX_STRIPPED = 'suffix_stripped_order_number';
    public const METHOD_MANUAL = 'manual_match';
    public const METHOD_MANUAL_CORRECTION = 'manual_correction';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'shipping_label_id',
        'order_number',
        'awb_number',
        'invoice_number',
        'status',
        'matching_method',
        'confidence_score',
        'secondary_verification',
        'discrepancy_details',
        'is_manually_matched',
        'matched_by_user_id',
        'matched_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'secondary_verification' => 'array',
            'discrepancy_details' => 'array',
            'is_manually_matched' => 'boolean',
            'matched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function shippingLabel(): BelongsTo
    {
        return $this->belongsTo(ShippingLabel::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }

    /**
     * Human-readable label for the matching method.
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->matching_method) {
            self::METHOD_EXACT_ORDER => 'Exact Order Match',
            self::METHOD_EXACT_PO => 'Exact PO Match',
            self::METHOD_NORMALIZED_ORDER => 'Normalized Order Match',
            self::METHOD_SUFFIX_STRIPPED => 'Suffix Stripped Match',
            self::METHOD_MANUAL => 'Manual Link',
            self::METHOD_MANUAL_CORRECTION => 'Manual Correction',
            default => 'System Match',
        };
    }

    // Scopes
    public function scopeMatched(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MATCHED);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEEDS_REVIEW);
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNMATCHED);
    }

    public function scopeDuplicate(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DUPLICATE);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
