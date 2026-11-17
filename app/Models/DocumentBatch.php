<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'batch_name',
        'total_files',
        'total_pages',
        'status',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_HAS_ERRORS = 'has_errors';
    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'batch_id');
    }

    /**
     * Recalculate totals and status based on child documents.
     */
    public function recalculateStatus(): void
    {
        $docs = $this->documents()->get();
        $this->total_files = $docs->count();
        $this->total_pages = $docs->sum(fn ($d) => $d->total_pages ?: $d->page_count ?: 1);

        $failed = $docs->where('extraction_status', Document::STATUS_FAILED)->count();
        $processing = $docs->whereIn('extraction_status', [Document::STATUS_PENDING, Document::STATUS_PROCESSING])->count();
        $completed = $docs->where('extraction_status', Document::STATUS_COMPLETED)->count();

        if ($processing > 0) {
            $this->status = self::STATUS_PROCESSING;
        } elseif ($failed > 0 && $completed > 0) {
            $this->status = self::STATUS_HAS_ERRORS;
        } elseif ($failed > 0 && $completed === 0) {
            $this->status = self::STATUS_FAILED;
        } else {
            $this->status = self::STATUS_COMPLETED;
        }

        $this->save();
    }
}
