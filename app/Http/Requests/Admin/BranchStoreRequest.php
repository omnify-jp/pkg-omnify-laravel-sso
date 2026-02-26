<?php

namespace Omnify\SsoClient\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BranchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:branches,slug'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'is_active' => ['boolean'],
            'is_headquarters' => ['boolean'],
        ];
    }
}
