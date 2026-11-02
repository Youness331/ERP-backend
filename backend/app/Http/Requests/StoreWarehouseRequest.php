<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
