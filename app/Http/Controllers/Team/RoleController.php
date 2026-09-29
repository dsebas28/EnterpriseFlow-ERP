<?php

namespace App\Http\Controllers\Team;

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SaveRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\SaveRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('team/Roles', [
            'roles' => RoleResource::collection(
                Role::with('permissionRecords')->withCount('memberships')->orderByDesc('is_system')->orderBy('id')->get(),
            ),
            'permissionGroups' => Permission::grouped(),
        ]);
    }

    public function store(SaveRoleRequest $request, SaveRole $save): RedirectResponse
    {
        $role = $save->handle(null, $request->validated('name'), $request->validated('description'), $request->permissions());

        return back()->with('status', "Role {$role->name} created.");
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRole $save): RedirectResponse
    {
        $save->handle($role, $request->validated('name'), $request->validated('description'), $request->permissions());

        return back()->with('status', "Role {$role->name} updated.");
    }

    public function destroy(Role $role, DeleteRole $delete): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $delete->handle($role);

        return back()->with('status', 'Role deleted.');
    }
}
