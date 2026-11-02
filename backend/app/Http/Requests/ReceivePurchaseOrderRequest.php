<?php

namespace App\Http\Requests;

class ReceivePurchaseOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // purchase_order_line_id => { quantity, unit_price }
            'lines' => ['sometimes', 'array'],
            'lines.*.quantity' => ['sometimes', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'received_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
