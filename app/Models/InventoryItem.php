<?php

namespace App\Models;

use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use BelongsToUser;

    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'product_id', 'sku', 'condition', 'condition_note', 'quantity',
        'cost', 'suggested_price', 'list_price', 'currency', 'status', 'location', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'condition' => Condition::class,
            'status' => InventoryStatus::class,
            'quantity' => 'integer',
            'cost' => 'decimal:2',
            'suggested_price' => 'decimal:2',
            'list_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
