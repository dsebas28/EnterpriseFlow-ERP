<?php

namespace App\Http\Requests\Team;

use App\Models\Role;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class UpdateMemberRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('membership')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', TenantRule::exists('roles')],
        ];
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::whereKey($this->validated('role_ids'))->get();
    }
}
