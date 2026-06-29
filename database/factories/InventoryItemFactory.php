<?php

namespace Database\Factories;

use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'condition' => fake()->randomElement(Condition::cases()),
            'quantity' => 1,
            'cost' => fake()->randomFloat(2, 1, 10),
            'currency' => 'GBP',
            'status' => InventoryStatus::Draft,
        ];
    }
}
