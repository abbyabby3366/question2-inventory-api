<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_creates_a_product_with_suppliers(): void
    {
        $category = Category::factory()->create();
        [$supplierA, $supplierB] = Supplier::factory()->count(2)->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'sku' => ' wm-001 ',
            'price' => 49.90,
            'stock_quantity' => 120,
            'suppliers' => [
                ['id' => $supplierA->id, 'cost_price' => 30.00],
                ['id' => $supplierB->id],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Wireless Mouse')
            ->assertJsonPath('data.sku', 'WM-001')            // mutator normalised it
            ->assertJsonPath('data.price', '49.90')
            ->assertJsonPath('data.stock_status', 'in_stock')  // accessor
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(2, 'data.suppliers');

        $product = Product::firstOrFail();
        $this->assertSame('WM-001', $product->sku);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'cost_price' => '30.00',
        ]);
    }

    public function test_it_validates_product_creation(): void
    {
        Product::factory()->create(['sku' => 'TAKEN-1']);

        $this->postJson('/api/products', [
            'category_id' => 999,
            'sku' => 'taken-1',
            'price' => -1,
            'stock_quantity' => 1.5,
            'suppliers' => [['id' => 999]],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'category_id', 'name', 'sku', 'price', 'stock_quantity', 'suppliers.0.id',
        ]);

        $this->assertDatabaseCount('products', 1);
    }

    public function test_it_shows_a_product_and_returns_404_for_unknown_ids(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku);

        $this->getJson('/api/products/999999')->assertNotFound();
    }

    public function test_it_partially_updates_a_product_and_syncs_suppliers(): void
    {
        $product = Product::factory()->hasAttached(Supplier::factory(), ['cost_price' => 1])->create([
            'price' => 10, 'stock_quantity' => 100,
        ]);
        $newSupplier = Supplier::factory()->create();

        $this->putJson("/api/products/{$product->id}", [
            'stock_quantity' => 0,
            'suppliers' => [['id' => $newSupplier->id, 'cost_price' => 7.5]],
        ])->assertOk()
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.stock_status', 'out_of_stock')
            ->assertJsonPath('data.price', '10.00')            // untouched
            ->assertJsonCount(1, 'data.suppliers')
            ->assertJsonPath('data.suppliers.0.id', $newSupplier->id)
            ->assertJsonPath('data.suppliers.0.cost_price', '7.50');
    }

    public function test_update_allows_keeping_its_own_sku_but_not_another_products(): void
    {
        $product = Product::factory()->create(['sku' => 'MINE-1']);
        Product::factory()->create(['sku' => 'OTHER-1']);

        $this->patchJson("/api/products/{$product->id}", ['sku' => 'mine-1'])->assertOk();
        $this->patchJson("/api/products/{$product->id}", ['sku' => 'OTHER-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_it_soft_deletes_a_product_and_can_restore_it(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted($product);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->getJson('/api/products')->assertJsonCount(0, 'data');

        $this->postJson("/api/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $this->assertNotSoftDeleted($product);
        $this->getJson('/api/products')->assertJsonCount(1, 'data');
    }
}

