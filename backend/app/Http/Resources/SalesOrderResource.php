<?php

namespace App\Http\Resources;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesOrder */
class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'order_date' => $this->order_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'lines' => SalesOrderLineResource::collection($this->whenLoaded('lines')),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'is_fully_delivered' => $this->whenLoaded('lines', fn () => $this->isFullyDelivered()),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
