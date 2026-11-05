<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is already behind auth:sanctum.
    }

    /**
     * Normalise the SKU before the unique check, so validation matches what the model mutator stores.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('products', 'sku')],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            ...$this->supplierRules(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function supplierRules(): array
    {
        return [
            'suppliers' => ['sometimes', 'array', 'max:50'],
            'suppliers.*.id' => ['required', 'integer', 'distinct', 'exists:suppliers,id'],
            'suppliers.*.cost_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * Product attributes only (no relationship payload).
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        return $this->safe()->except('suppliers');
    }

    /**
     * Supplier sync payload in the shape BelongsToMany::sync() expects, or null when the client
     * did not send "suppliers" at all (so existing links are left untouched).
     *
     * @return array<int, array{cost_price: string|null}>|null
     */
    public function supplierSyncData(): ?array
    {
        if (! $this->has('suppliers')) {
            return null;
        }

        $sync = [];
        foreach ($this->validated('suppliers') as $supplier) {
            $sync[(int) $supplier['id']] = ['cost_price' => $supplier['cost_price'] ?? null];
        }

        return $sync;
    }
}

