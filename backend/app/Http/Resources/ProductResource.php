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
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'unit' => $this->unit,
            'purchase_price' => (float) $this->purchase_price,
            'sale_price' => (float) $this->sale_price,
            'cost_price' => (float) $this->cost_price,
            'tax_rate' => (float) $this->tax_rate,
            'reorder_level' => (float) $this->reorder_level,
            'track_stock' => (bool) $this->track_stock,
            'is_active' => (bool) $this->is_active,
            'stock_quantity' => $this->when(
                isset($this->stocks_sum_quantity),
                fn () => round((float) $this->stocks_sum_quantity, 3)
            ),
            'reserved_quantity' => $this->when(
                isset($this->stocks_sum_reserved_quantity),
                fn () => round((float) $this->stocks_sum_reserved_quantity, 3)
            ),
            'is_low_stock' => $this->when(
                isset($this->stocks_sum_quantity),
                fn () => (float) $this->stocks_sum_quantity <= (float) $this->reorder_level
            ),
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
