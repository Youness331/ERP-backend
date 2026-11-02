<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class UpdateInvoiceRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        $rules = $this->lineRules(requireProduct: false);
        $rules['lines'] = ['sometimes', 'array', 'min:1'];

        return $rules + [
            'client_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
