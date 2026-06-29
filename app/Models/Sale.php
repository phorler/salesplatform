<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'inventory_item_id', 'listing_id', 'channel', 'external_order_id',
        'external_order_item_id', 'quantity', 'sale_price', 'fees', 'currency',
        'buyer_marketplace', 'sold_at', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sale_price' => 'decimal:2',
            'fees' => 'decimal:2',
            'sold_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Net proceeds for this sale line: gross minus channel fees minus item cost.
     */
    public function profit(): ?string
    {
        if ($this->sale_price === null) {
            return null;
        }

        $gross = bcmul((string) $this->sale_price, (string) $this->quantity, 2);
        $fees = bcmul((string) ($this->fees ?? '0'), (string) $this->quantity, 2);
        $unitCost = $this->inventoryItem?->cost ?? '0';
        $cost = bcmul((string) $unitCost, (string) $this->quantity, 2);

        return bcsub(bcsub($gross, $fees, 2), $cost, 2);
    }
}
