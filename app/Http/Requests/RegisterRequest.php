<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:1', 'max:80'], 'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', Password::min(6), 'same:passwordConfirmation'], 'passwordConfirmation' => ['required', 'string'], 'deviceName' => ['sometimes', 'string', 'max:100']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
