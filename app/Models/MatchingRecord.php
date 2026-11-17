<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'shipping_document_id',
        'po_reference',
        'match_type',
        'status',
        'discrepancy_details',
        'tolerance_percentage',
        'matched_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'discrepancy_details' => 'array',
            'tolerance_percentage' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function shippingDocument(): BelongsTo
    {
        return $this->belongsTo(ShippingDocument::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }
}
