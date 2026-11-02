<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderLine */
class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'received_quantity' => (float) $this->received_quantity,
            'remaining_quantity' => (float) $this->remaining_quantity,
            'unit_price' => (float) $this->unit_price,
            'discount' => (float) $this->discount,
            'tax_rate' => (float) $this->tax_rate,
            'line_total' => (float) $this->line_total,
        ];
    }
}
