<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    /**
     * Authorization is handled by the `permission` middleware on the route.
     */
    public function authorize(): bool
    {
        return true;
    }
}
