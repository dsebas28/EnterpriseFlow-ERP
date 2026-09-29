<?php

namespace App\Actions\Roles;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Role;

final class DeleteRole
{
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw new BusinessRuleViolation('System roles cannot be deleted.');
        }

        if ($role->memberships()->exists()) {
            throw new BusinessRuleViolation('Reassign the members of this role before deleting it.');
        }

        $role->delete();
    }
}
