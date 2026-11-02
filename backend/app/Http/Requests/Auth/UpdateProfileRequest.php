<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes', 'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
