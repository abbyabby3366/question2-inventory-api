<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 1, 2000),
            'stock_quantity' => fake()->numberBetween(11, 500),
            'low_stock_threshold' => 10,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stock_quantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(['stock_quantity' => 5, 'low_stock_threshold' => 10]);
    }
}
