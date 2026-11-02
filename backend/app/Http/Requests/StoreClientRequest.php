<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreClientRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'ice' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'rc' => ['nullable', 'string', 'max:20'],
            'patente' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
