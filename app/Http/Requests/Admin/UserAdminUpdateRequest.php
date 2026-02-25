<?php

namespace Omnify\SsoClient\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserAdminUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
        ];
    }
}
