<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class StorePurchaseOrderRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules();
    }
}
