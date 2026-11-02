<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('warehouses', 'code')->ignore($this->route('warehouse')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
