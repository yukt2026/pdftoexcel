<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'item_code',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'item_rate',
        'discount',
        'taxable_value',
        'tax_rate',
        'tax_amount',
        'cgst',
        'sgst',
        'igst',
        'total_price',
        'category',
        'is_extra_charge',
        'matched_po_item_id',
        'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'item_rate' => 'decimal:4',
            'discount' => 'decimal:2',
            'taxable_value' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'cgst' => 'decimal:2',
            'sgst' => 'decimal:2',
            'igst' => 'decimal:2',
            'total_price' => 'decimal:2',
            'is_extra_charge' => 'boolean',
            'confidence_score' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
