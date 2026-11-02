<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
        ];
    }
}
