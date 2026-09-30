<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\ChangeMembershipStatus;
use App\Actions\Team\UpdateMemberRoles;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateMemberRolesRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\RoleResource;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        Gate::authorize('viewAny', Membership::class);

        $search = trim((string) $request->query('search', ''));

        $members = Membership::query()
            ->where('company_user.company_id', $tenant->idOrFail())
            ->with(['user', 'roles'])
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                // whereLike is case-insensitive on every engine (ILIKE on PostgreSQL).
                ->whereLike('users.name', "%{$search}%")
                ->orWhereLike('users.email', "%{$search}%")))
            ->orderBy('users.name')
            ->select('company_user.*')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('team/Members', [
            'members' => MemberResource::collection($members),
            'invitations' => InvitationResource::collection(
                Invitation::pending()->with(['role', 'inviter'])->latest()->get(),
            ),
            'roles' => RoleResource::collection(
                Role::with('permissionRecords')->orderByDesc('is_system')->orderBy('id')->get(),
            ),
            'filters' => ['search' => $search],
            'can' => [
                'manageRoles' => $request->user()?->can('create', Role::class) ?? false,
            ],
        ]);
    }

    public function updateRoles(UpdateMemberRolesRequest $request, Membership $membership, UpdateMemberRoles $action): RedirectResponse
    {
        $action->handle($request->user(), $membership, $request->roles());

        return back()->with('status', 'Roles updated.');
    }

    public function suspend(Request $request, Membership $membership, ChangeMembershipStatus $action): RedirectResponse
    {
        Gate::authorize('update', $membership);

        $action->handle($request->user(), $membership, MembershipStatus::Suspended);

        return back()->with('status', 'Member suspended.');
    }

    public function reactivate(Request $request, Membership $membership, ChangeMembershipStatus $action): RedirectResponse
    {
        Gate::authorize('update', $membership);

        $action->handle($request->user(), $membership, MembershipStatus::Active);

        return back()->with('status', 'Member reactivated.');
    }
}
