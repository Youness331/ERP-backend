<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreProductRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:30'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'opening_stock' => ['nullable', 'array'],
            'opening_stock.*.warehouse_id' => ['required_with:opening_stock', 'integer', 'exists:warehouses,id'],
            'opening_stock.*.quantity' => ['required_with:opening_stock', 'numeric', 'min:0'],
        ];
    }
}
