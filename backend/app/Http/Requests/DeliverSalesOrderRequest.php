<?php

namespace App\Http\Requests;

class DeliverSalesOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // sales_order_line_id => quantity to ship now
            'quantities' => ['sometimes', 'array'],
            'quantities.*' => ['numeric', 'gt:0'],
            'delivered_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
