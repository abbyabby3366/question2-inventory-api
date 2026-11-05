<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::all();

        Category::all()->each(function (Category $category) use ($suppliers) {
            // A realistic mix of stock levels so every filter returns something.
            $products = Product::factory()->count(8)->for($category)->create()
                ->concat(Product::factory()->count(2)->lowStock()->for($category)->create())
                ->concat(Product::factory()->count(1)->outOfStock()->for($category)->create());

            $products->each(function (Product $product) use ($suppliers) {
                $sync = [];
                foreach ($suppliers->random(fake()->numberBetween(1, 3)) as $supplier) {
                    $sync[$supplier->id] = ['cost_price' => round((float) $product->price * 0.6, 2)];
                }
                $product->suppliers()->sync($sync);
            });
        });

        // One soft-deleted product, to demonstrate restore.
        Product::query()->latest('id')->firstOrFail()->delete();
    }
}

