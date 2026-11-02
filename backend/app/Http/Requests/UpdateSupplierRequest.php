<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('suppliers', 'code')->ignore($this->route('supplier')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ice' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:20'],
            'rc' => ['sometimes', 'nullable', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
