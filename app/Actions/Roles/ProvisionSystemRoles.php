<?php

namespace App\Actions\Roles;

use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Copies the SystemRole templates into a company. Idempotent: existing
 * roles are left untouched so customised permissions survive re-runs.
 */
final class ProvisionSystemRoles
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return Collection<string, Role> roles keyed by slug
     */
    public function handle(Company $company): Collection
    {
        return $this->tenant->run($company, function (): Collection {
            return collect(SystemRole::cases())->mapWithKeys(function (SystemRole $template): array {
                $role = Role::firstWhere('slug', $template->value);

                if ($role === null) {
                    $role = new Role([
                        'name' => $template->label(),
                        'slug' => $template->value,
                        'description' => $template->description(),
                    ]);
                    $role->is_system = true;
                    $role->save();

                    // Owner permissions are implicit (always all); nothing to store.
                    if ($template !== SystemRole::Owner) {
                        $role->syncPermissions($template->defaultPermissions());
                    }
                }

                return [$template->value => $role];
            });
        });
    }
}
