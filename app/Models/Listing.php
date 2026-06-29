<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'inventory_item_id', 'marketplace_account_id', 'channel',
        'external_id', 'sku', 'submission_id', 'status', 'issues',
        'listed_price', 'listed_quantity', 'status_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'issues' => 'array',
            'listed_price' => 'decimal:2',
            'listed_quantity' => 'integer',
            'status_checked_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function marketplaceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAccount::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
