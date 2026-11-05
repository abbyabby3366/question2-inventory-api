<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SupplierResource;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only lookup endpoints so API clients can build the product filters and forms.
 */
class LookupController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->withCount('products')->orderBy('name')->get()
        );
    }

    public function suppliers(): AnonymousResourceCollection
    {
        return SupplierResource::collection(
            Supplier::query()->orderBy('name')->get()
        );
    }
}

