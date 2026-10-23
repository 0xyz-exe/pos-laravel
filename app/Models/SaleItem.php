<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = ['product_id', 'product_name', 'sku', 'quantity', 'unit_price_minor', 'line_total_minor'];
    protected function casts(): array { return ['quantity' => 'integer', 'unit_price_minor' => 'integer', 'line_total_minor' => 'integer']; }
    public function sale(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Sale::class); }
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Product::class); }
}
