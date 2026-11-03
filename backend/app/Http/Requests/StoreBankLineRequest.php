<?php

namespace App\Http\Requests;

use App\Enums\PaymentDirection;
use Illuminate\Validation\Rule;

class StoreBankLineRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'direction' => ['nullable', Rule::enum(PaymentDirection::class)],
            'value_date' => ['nullable', 'date'],
        ];
    }
}
