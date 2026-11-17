<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_item_id',
        'field_name',
        'original_value',
        'updated_value',
        'updated_by',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
