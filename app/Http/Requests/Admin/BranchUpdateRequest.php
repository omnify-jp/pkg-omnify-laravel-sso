<?php

namespace Omnify\SsoClient\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchUpdateRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', Rule::unique('branches', 'slug')->ignore($this->route('branch'))],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'is_active' => ['boolean'],
            'is_headquarters' => ['boolean'],
        ];
    }
}
