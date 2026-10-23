<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['product_id', 'user_id', 'sale_id', 'type', 'quantity_delta', 'balance_after', 'note'];
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Product::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function sale(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Sale::class); }
}
