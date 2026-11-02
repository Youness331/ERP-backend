<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class UpdateSalesOrderRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        $rules = $this->lineRules();
        $rules['lines'] = ['sometimes', 'array', 'min:1'];

        return $rules + [
            'client_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:warehouses,id'],
            'order_date' => ['sometimes', 'nullable', 'date'],
            'delivery_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
