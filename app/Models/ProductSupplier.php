<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductSupplier extends Pivot
{
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
        ];
    }
}

