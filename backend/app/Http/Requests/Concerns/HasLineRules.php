<?php

namespace App\Http\Requests\Concerns;

trait HasLineRules
{
    /**
     * Validation rules shared by purchase orders, sales orders and invoices.
     *
     * @return array<string, mixed>
     */
    protected function lineRules(bool $requireProduct = true): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => $requireProduct
                ? ['required', 'integer', 'exists:products,id']
                : ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
