<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:60'],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'purchase_price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'numeric', 'between:0,100'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
