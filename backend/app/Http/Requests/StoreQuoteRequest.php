<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class StoreQuoteRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quote_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules();
    }
}
