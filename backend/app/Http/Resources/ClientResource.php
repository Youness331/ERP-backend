<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'ice' => $this->ice,
            'tax_id' => $this->tax_id,
            'rc' => $this->rc,
            'patente' => $this->patente,
            'credit_limit' => (float) $this->credit_limit,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'sales_orders_count' => $this->whenCounted('salesOrders'),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
