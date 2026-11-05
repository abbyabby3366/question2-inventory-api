<?php

namespace App\Enums;

enum StockLevel: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public static function fromQuantity(int $quantity, int $lowStockThreshold): self
    {
        if ($quantity === 0) {
            return self::OutOfStock;
        }

        if ($quantity <= $lowStockThreshold) {
            return self::LowStock;
        }

        return self::InStock;
    }
}

