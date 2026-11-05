<?php

namespace App\Http\Requests\Product;

use App\Enums\StockLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public const SORTABLE = ['name', 'price', 'stock_quantity', 'created_at'];

    public const DEFAULT_PER_PAGE = 15;

    public function authorize(): bool
    {
        return true; // Route is already behind auth:sanctum.
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gte:min_price'],
            'stock_level' => ['sometimes', Rule::enum(StockLevel::class)],
            'min_stock' => ['sometimes', 'integer', 'min:0'],
            'max_stock' => ['sometimes', 'integer', 'min:0', 'gte:min_stock'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in($this->sortOptions())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'category_id', 'min_price', 'max_price', 'stock_level', 'min_stock', 'max_stock', 'search',
        ]);
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sort(): array
    {
        $sort = $this->validated('sort', '-created_at');

        return str_starts_with($sort, '-')
            ? [substr($sort, 1), 'desc']
            : [$sort, 'asc'];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', self::DEFAULT_PER_PAGE);
    }

    /**
     * @return list<string>
     */
    private function sortOptions(): array
    {
        $options = [];
        foreach (self::SORTABLE as $column) {
            $options[] = $column;
            $options[] = "-{$column}";
        }

        return $options;
    }
}

