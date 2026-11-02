<?php

namespace App\Http\Resources;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Warehouse */
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'stocks_count' => $this->whenCounted('stocks'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
