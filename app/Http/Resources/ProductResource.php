<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'stock_status' => $this->stock_status->value,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'suppliers' => SupplierResource::collection($this->whenLoaded('suppliers')),
            'created_at' => Timestamp::format($this->created_at),
            'updated_at' => Timestamp::format($this->updated_at),
            'deleted_at' => $this->when($this->deleted_at !== null, fn () => Timestamp::format($this->deleted_at)),
        ];
    }
}
