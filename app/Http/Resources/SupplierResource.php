<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            // Only present when loaded through a product (pivot table product_supplier).
            'cost_price' => $this->whenPivotLoaded('product_supplier', fn () => $this->pivot->cost_price),
        ];
    }
}
