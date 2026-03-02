<?php

namespace Omnify\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Omnify\Core\Models\User;

class OrganizationUserStoreRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'name' => [
                Rule::requiredIf(fn () => ! User::where('email', $this->input('email'))->exists()),
                'nullable', 'string', 'max:255',
            ],
            'role_id' => ['required', 'string', 'exists:roles,id'],
            'console_branch_id' => [
                'nullable', 'string',
                Rule::exists('branches', 'console_branch_id')
                    ->where('console_organization_id', $this->route('organization')?->console_organization_id),
            ],
        ];
    }
}
