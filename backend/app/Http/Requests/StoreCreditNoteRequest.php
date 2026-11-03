<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLineRules;

class StoreCreditNoteRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        $rules = $this->lineRules(requireProduct: false);

        // Omitting the lines credits the whole remaining balance.
        $rules['lines'] = ['sometimes', 'array', 'min:1'];

        return $rules;
    }
}
