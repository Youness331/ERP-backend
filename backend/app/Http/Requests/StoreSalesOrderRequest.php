<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class StoreSalesOrderRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'order_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules();
    }
}
