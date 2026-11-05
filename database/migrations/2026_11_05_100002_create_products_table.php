<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a category that still has products cannot be removed by accident.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('sku', 64)->unique();
            $table->text('description')->nullable();
            // Money is stored as an exact DECIMAL, never as a float.
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock_quantity');
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->timestamps();
            $table->softDeletes();

            // Supports the list filters (price range / stock level) and the default sort.
            $table->index('price');
            $table->index('stock_quantity');
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
        Schema::dropIfExists('products');
    }
};

