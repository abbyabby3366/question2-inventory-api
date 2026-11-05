<?php

namespace App\Models;

use App\Enums\StockLevel;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'low_stock_threshold',
    ];

    /**
     * Mirrors the column default in the migration, so a freshly created model (not re-read from the
     * DB) still has the value.
     */
    protected $attributes = [
        'low_stock_threshold' => 10,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /* ---------------------------------------------------------------------
     | Relationships
     * -------------------------------------------------------------------*/

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->using(ProductSupplier::class)
            ->withPivot('cost_price')
            ->withTimestamps();
    }

    /* ---------------------------------------------------------------------
     | Accessors & mutators
     * -------------------------------------------------------------------*/

    /**
     * Mutator: SKUs are normalised (trimmed and upper-cased) on write, so "abc-1" and " ABC-1 "
     * map to the same unique value.
     */
    protected function sku(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    /**
     * Accessor: derived stock level (in_stock / low_stock / out_of_stock). This is not a DB column.
     */
    protected function stockStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => StockLevel::fromQuantity($this->stock_quantity, $this->low_stock_threshold),
        );
    }

    /* ---------------------------------------------------------------------
     | Query scopes
     * -------------------------------------------------------------------*/

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopePriceBetween(Builder $query, ?string $min, ?string $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('price', '<=', $max));
    }

    public function scopeStockLevel(Builder $query, StockLevel $level): Builder
    {
        return match ($level) {
            StockLevel::OutOfStock => $query->where('stock_quantity', 0),
            StockLevel::LowStock => $query->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
            StockLevel::InStock => $query->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
        };
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%"));
    }

    /**
     * Applies the validated list filters (see IndexProductRequest). Only filters that were
     * actually supplied are applied.
     *
     * @param  array{category_id?: int, min_price?: string, max_price?: string, stock_level?: string, min_stock?: int, max_stock?: int, search?: string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['category_id']), fn (Builder $q) => $q->inCategory((int) $filters['category_id']))
            ->when(
                isset($filters['min_price']) || isset($filters['max_price']),
                fn (Builder $q) => $q->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
            )
            ->when(isset($filters['stock_level']), fn (Builder $q) => $q->stockLevel(StockLevel::from($filters['stock_level'])))
            ->when(isset($filters['min_stock']), fn (Builder $q) => $q->where('stock_quantity', '>=', (int) $filters['min_stock']))
            ->when(isset($filters['max_stock']), fn (Builder $q) => $q->where('stock_quantity', '<=', (int) $filters['max_stock']))
            ->when(isset($filters['search']), fn (Builder $q) => $q->search($filters['search']));
    }
}
