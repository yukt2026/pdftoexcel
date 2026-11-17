<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tax_number',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'payment_terms',
        'bank_details',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'bank_details' => 'array',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
