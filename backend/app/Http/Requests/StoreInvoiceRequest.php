<?php

namespace App\Http\Requests;

use App\Enums\InvoiceType;
use App\Http\Requests\Concerns\HasLineRules;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends ApiRequest
{
    use HasLineRules;

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'type' => ['nullable', Rule::enum(InvoiceType::class)],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules(requireProduct: false);
    }
}
