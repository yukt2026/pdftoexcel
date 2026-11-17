<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'export_name',
        'format',
        'file_path',
        'total_records',
        'filter_criteria',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'filter_criteria' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
