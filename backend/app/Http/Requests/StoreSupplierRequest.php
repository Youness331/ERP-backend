<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSupplierRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('suppliers', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'ice' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'rc' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
