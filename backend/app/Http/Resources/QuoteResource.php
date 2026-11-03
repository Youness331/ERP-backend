<?php

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quote */
class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'quote_date' => $this->quote_date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'lines' => QuoteLineResource::collection($this->whenLoaded('lines')),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => SalesOrderResource::make($this->whenLoaded('salesOrder')),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'can_be_converted' => $this->status->isConvertible() && ! $this->hasBeenConverted(),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
