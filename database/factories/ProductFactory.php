<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'isbn13' => (string) fake()->unique()->numerify('978#########'),
            'isbn10' => null,
            'title' => fake()->sentence(3),
            'authors' => [fake()->name()],
            'publisher' => fake()->company(),
            'published_year' => fake()->numberBetween(1950, 2025),
            'cover_url' => null,
            'fetched_at' => now(),
        ];
    }
}
