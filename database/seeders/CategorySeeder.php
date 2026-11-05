<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => 'Phones, laptops and accessories',
            'Home & Kitchen' => 'Appliances and kitchenware',
            'Office Supplies' => 'Stationery and office equipment',
            'Sports & Outdoors' => 'Fitness gear and outdoor equipment',
            'Groceries' => 'Packaged food and beverages',
        ];

        foreach ($categories as $name => $description) {
            Category::create(['name' => $name, 'slug' => $name, 'description' => $description]);
        }
    }
}

