<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'page_number',
        'extracted_text',
        'char_count',
        'is_scanned',
        'ocr_applied',
        'ocr_provider',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'is_scanned' => 'boolean',
            'ocr_applied' => 'boolean',
            'char_count' => 'integer',
            'page_number' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
