<?php

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;

/**
 * Supports partial updates (PATCH semantics on PUT/PATCH): every field is optional, but when it
 * is present it must be valid.
 */
class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('products', 'sku')->ignore($productId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            ...$this->supplierRules(),
        ];
    }
}

