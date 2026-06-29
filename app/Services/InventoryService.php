<?php

namespace App\Services;

use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

class InventoryService
{
    /**
     * Create an inventory item for a user from a catalogue product. A unique SKU
     * is generated when one isn't supplied.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFromProduct(User $user, Product $product, array $attributes): InventoryItem
    {
        $condition = $attributes['condition'] instanceof Condition
            ? $attributes['condition']
            : Condition::from($attributes['condition']);

        return $user->inventoryItems()->create([
            'product_id' => $product->id,
            'sku' => $attributes['sku'] ?? $this->generateSku($user, $product, $condition),
            'condition' => $condition,
            'condition_note' => $attributes['condition_note'] ?? null,
            'quantity' => $attributes['quantity'] ?? 1,
            'cost' => $attributes['cost'] ?? null,
            'suggested_price' => $attributes['suggested_price'] ?? null,
            'list_price' => $attributes['list_price'] ?? null,
            'currency' => $attributes['currency'] ?? 'GBP',
            'status' => InventoryStatus::Draft,
            'location' => $attributes['location'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    /**
     * A readable, per-seller-unique SKU: ISBN13-<conditioncode>-<rand>.
     */
    public function generateSku(User $user, Product $product, Condition $condition): string
    {
        $code = match ($condition) {
            Condition::New => 'N',
            Condition::LikeNew => 'LN',
            Condition::VeryGood => 'VG',
            Condition::Good => 'G',
            Condition::Acceptable => 'A',
        };

        do {
            $sku = sprintf('%s-%s-%s', $product->isbn13, $code, strtoupper(Str::random(4)));
        } while ($user->inventoryItems()->where('sku', $sku)->exists());

        return $sku;
    }
}
