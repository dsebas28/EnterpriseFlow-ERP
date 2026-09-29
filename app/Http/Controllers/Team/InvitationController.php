<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\InviteMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\InviteMemberRequest;
use App\Models\Invitation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function store(InviteMemberRequest $request, InviteMember $invite, TenantContext $tenant): RedirectResponse
    {
        $invitation = $invite->handle(
            $tenant->companyOrFail(),
            $request->user(),
            $request->validated('email'),
            $request->role(),
        );

        return back()->with('status', "Invitation sent to {$invitation->email}.");
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->forceFill(['revoked_at' => now()])->save();

        return back()->with('status', 'Invitation revoked.');
    }
}
