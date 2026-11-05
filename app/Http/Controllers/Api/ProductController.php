<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ProductCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private const RELATIONS = ['category', 'suppliers'];

    public function __construct(private readonly ProductCache $cache) {}

    /**
     * GET /api/products: paginated list with filters (category, price range, stock level, search).
     */
    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        [$sortColumn, $sortDirection] = $request->sort();

        $products = $this->cache->rememberIndex($request->validated(), fn () => Product::query()
            ->with(self::RELATIONS)
            ->filter($request->filters())
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id')                       // stable order across pages
            ->paginate($request->perPage())
            ->withQueryString());

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->productAttributes());

            $suppliers = $request->supplierSyncData();
            if ($suppliers !== null) {
                $product->suppliers()->sync($suppliers);
            }

            return $product;
        });

        return (new ProductResource($product->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::RELATIONS));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->productAttributes());

            $suppliers = $request->supplierSyncData();
            if ($suppliers !== null) {
                $changes = $product->suppliers()->sync($suppliers);

                // Pivot changes don't fire model events. Touch the product so updated_at reflects the
                // change and the cache observer runs.
                if ($changes['attached'] !== [] || $changes['detached'] !== [] || $changes['updated'] !== []) {
                    $product->touch();
                }
            }
        });

        return new ProductResource($product->refresh()->load(self::RELATIONS));
    }

    /**
     * Soft delete: the row is kept (deleted_at is set) and can be restored.
     */
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    /**
     * POST /api/products/{id}/restore: undo a soft delete.
     */
    public function restore(Product $product): ProductResource
    {
        abort_unless($product->trashed(), Response::HTTP_CONFLICT, 'Product is not deleted.');

        $product->restore();

        return new ProductResource($product->load(self::RELATIONS));
    }
}

