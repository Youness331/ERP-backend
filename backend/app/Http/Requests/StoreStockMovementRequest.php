<?php

namespace App\Http\Requests;

use App\Enums\StockMovementType;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends ApiRequest
{
    public function rules(): array
    {
        $isAdjustment = $this->input('type') === StockMovementType::Adjustment->value;

        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => $isAdjustment
                ? ['required', 'numeric', 'min:0']
                : ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'La quantité ne peut pas être négative.',
            'quantity.gt' => 'La quantité doit être supérieure à zéro.',
        ];
    }
}
