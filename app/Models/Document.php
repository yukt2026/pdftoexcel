<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_SHIPPING_LABEL = 'shipping_label';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXTRACTED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'user_id',
        'invoice_id',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'file_hash',
        'total_pages',
        'extraction_status',
        'error_message',
        'raw_text',
        'page_wise_text',
        'is_scanned',
        'has_password',
        'ocr_status',
        'ocr_provider',
        'file_type',
        'page_count',
        'hash',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_scanned' => 'boolean',
            'has_password' => 'boolean',
            'page_wise_text' => 'array',
        ];
    }

    protected static function booted()
    {
        static::saving(function (Document $doc) {
            // Keep legacy and standard column pairs synchronized
            if ($doc->file_hash && empty($doc->hash)) {
                $doc->hash = $doc->file_hash;
            } elseif ($doc->hash && empty($doc->file_hash)) {
                $doc->file_hash = $doc->hash;
            }

            if ($doc->total_pages && empty($doc->page_count)) {
                $doc->page_count = $doc->total_pages;
            } elseif ($doc->page_count && empty($doc->total_pages)) {
                $doc->total_pages = $doc->page_count;
            }

            if ($doc->extraction_status && empty($doc->status)) {
                $doc->status = $doc->extraction_status;
            } elseif ($doc->status && empty($doc->extraction_status)) {
                $doc->extraction_status = $doc->status;
            }

            if (empty($doc->file_type)) {
                $doc->file_type = 'pdf';
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function extractionResult(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InvoiceExtractionResult::class);
    }

    public function shippingLabels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingLabel::class);
    }

    public function pages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentPage::class)->orderBy('page_number');
    }

    public function isInvoice(): bool
    {
        return $this->document_type === self::TYPE_INVOICE;
    }

    public function isShippingLabel(): bool
    {
        return $this->document_type === self::TYPE_SHIPPING_LABEL;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (float) $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        return number_format(max($bytes / 1024, 0.1), 1) . ' KB';
    }
}
