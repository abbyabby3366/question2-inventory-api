<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_returns_a_paginated_list_with_relations(): void
    {
        Product::factory()->count(20)->create();

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'sku', 'price', 'stock_quantity', 'stock_status', 'category' => ['id', 'name'], 'suppliers', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'total', 'per_page'],
            ]);
    }

    public function test_it_filters_by_category(): void
    {
        $electronics = Category::factory()->create();
        Product::factory()->count(3)->for($electronics)->create();
        Product::factory()->count(4)->create();

        $response = $this->getJson("/api/products?category_id={$electronics->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $product) {
            $this->assertSame($electronics->id, $product['category']['id']);
        }
    }

    public function test_it_filters_by_price_range(): void
    {
        Product::factory()->create(['price' => 5.00]);
        $inRange = Product::factory()->create(['price' => 50.00]);
        Product::factory()->create(['price' => 500.00]);

        $this->getJson('/api/products?min_price=10&max_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inRange->id)
            ->assertJsonPath('data.0.price', '50.00');
    }

    public function test_it_filters_by_stock_level(): void
    {
        $out = Product::factory()->outOfStock()->create();
        $low = Product::factory()->lowStock()->create();
        Product::factory()->count(2)->create(); // in stock

        $this->getJson('/api/products?stock_level=out_of_stock')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $out->id);

        $this->getJson('/api/products?stock_level=low_stock')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $low->id);

        $this->getJson('/api/products?stock_level=in_stock')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/products?min_stock=1&max_stock=5')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $low->id);
    }

    public function test_it_rejects_invalid_filters(): void
    {
        $this->getJson('/api/products?min_price=100&max_price=10&stock_level=plenty&per_page=1000&sort=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_price', 'stock_level', 'per_page', 'sort']);
    }

    public function test_cached_listing_is_invalidated_when_a_product_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Old name']);

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Old name');

        $this->patchJson("/api/products/{$product->id}", ['name' => 'New name'])->assertOk();

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'New name');
    }
}

