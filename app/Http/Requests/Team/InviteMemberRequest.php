<?php

namespace App\Http\Requests\Team;

use App\Models\Invitation;
use App\Models\Role;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'integer', TenantRule::exists('roles')],
        ];
    }

    public function role(): Role
    {
        return Role::findOrFail($this->integer('role_id'));
    }
}
