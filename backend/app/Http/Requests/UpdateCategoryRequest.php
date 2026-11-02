<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:140',
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => [
                'sometimes', 'nullable', 'integer', 'exists:categories,id',
                Rule::notIn([$this->route('category')?->id]),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
