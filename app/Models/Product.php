<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'sku', 'barcode', 'price_minor', 'stock', 'active'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'stock' => 'integer', 'active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
