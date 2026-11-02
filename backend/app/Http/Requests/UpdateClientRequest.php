<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateClientRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('clients', 'code')->ignore($this->route('client')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ice' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:20'],
            'rc' => ['sometimes', 'nullable', 'string', 'max:20'],
            'patente' => ['sometimes', 'nullable', 'string', 'max:20'],
            'credit_limit' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
