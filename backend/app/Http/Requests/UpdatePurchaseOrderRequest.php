<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class UpdatePurchaseOrderRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        $rules = $this->lineRules();
        $rules['lines'] = ['sometimes', 'array', 'min:1'];

        return $rules + [
            'supplier_id' => ['sometimes', 'required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:warehouses,id'],
            'order_date' => ['sometimes', 'nullable', 'date'],
            'expected_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
