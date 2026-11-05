<?php

namespace Tests\Unit;

use App\Enums\StockLevel;
use App\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductAttributesTest extends TestCase
{
    public static function stockProvider(): array
    {
        return [
            'zero is out of stock' => [0, 10, StockLevel::OutOfStock],
            'at threshold is low' => [10, 10, StockLevel::LowStock],
            'below threshold is low' => [1, 10, StockLevel::LowStock],
            'above threshold is in stock' => [11, 10, StockLevel::InStock],
        ];
    }

    #[DataProvider('stockProvider')]
    public function test_stock_status_accessor(int $quantity, int $threshold, StockLevel $expected): void
    {
        $product = new Product(['stock_quantity' => $quantity, 'low_stock_threshold' => $threshold]);

        $this->assertSame($expected, $product->stock_status);
    }

    public function test_sku_mutator_normalises_value(): void
    {
        $product = new Product(['sku' => '  abc-123 ']);

        $this->assertSame('ABC-123', $product->sku);
    }
}

