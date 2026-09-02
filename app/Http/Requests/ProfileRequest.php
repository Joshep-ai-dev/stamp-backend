<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'min:1', 'max:80'], 'email' => ['sometimes', 'required', 'email:rfc', 'max:255', 'unique:users,email,'.$this->user()->id], 'language' => ['sometimes', 'required', 'string', 'min:2', 'max:40'], 'nationality' => ['sometimes', 'nullable', 'string', 'max:100'], 'dateOfBirth' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'sex' => ['sometimes', 'nullable', 'in:M,F'], 'photoUri' => ['sometimes', 'nullable', 'string', 'max:2048']];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
