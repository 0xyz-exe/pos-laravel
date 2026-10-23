<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = ['cashier_id', 'receipt_number', 'payment_method', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'sold_at', 'voided_at', 'voided_by_id', 'void_reason'];

    public function isVoided(): bool { return $this->voided_at !== null; }

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'sold_at' => 'datetime'];
    }

    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
}
