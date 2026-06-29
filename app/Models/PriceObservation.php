<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time market reading for a book. Global (not user-scoped).
 */
class PriceObservation extends Model
{
    protected $fillable = [
        'product_id', 'source', 'asin', 'new_price', 'used_price',
        'sales_rank', 'currency', 'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'new_price' => 'decimal:2',
            'used_price' => 'decimal:2',
            'sales_rank' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
