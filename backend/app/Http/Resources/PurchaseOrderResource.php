<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'received_at' => $this->received_at?->toIso8601String(),
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'balance_due' => (float) $this->balance_due,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'notes' => $this->notes,
            'is_fully_received' => $this->whenLoaded('lines', fn () => $this->isFullyReceived()),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
