<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'document_type',
        'tracking_number',
        'carrier_name',
        'shipped_date',
        'delivery_date',
        'origin_address',
        'destination_address',
        'total_weight',
        'weight_unit',
        'total_packages',
        'shipping_cost',
        'status',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'shipped_date' => 'date',
            'delivery_date' => 'date',
            'total_weight' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'raw_data' => 'array',
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

    public function matchingRecords(): HasMany
    {
        return $this->hasMany(MatchingRecord::class);
    }
}
