<?php

namespace App\Http\Requests\Team;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()?->can('update', $role) ?? false
            : $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::values())],
        ];
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return array_map(
            fn (string $value) => Permission::from($value),
            array_values($this->validated('permissions')),
        );
    }
}
