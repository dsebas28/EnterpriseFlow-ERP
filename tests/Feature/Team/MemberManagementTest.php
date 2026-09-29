<?php

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function roleIds(Company $company, string ...$slugs): array
{
    return tenant()->run($company, fn () => Role::whereIn('slug', $slugs)->pluck('id')->all());
}

describe('listing', function () {
    it('lists only members of the active company', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        memberWithRole(SystemRole::Sales, $company, User::factory()->create(['name' => 'Alice Same']));
        memberWithRole(SystemRole::Owner, user: User::factory()->create(['name' => 'Bob Elsewhere']));

        $this->actingAs($admin)
            ->get(route('team.members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('team/Members')
                ->has('members.data', 2)
                ->where('members.data', fn ($members) => ! collect($members)->pluck('user.name')->contains('Bob Elsewhere')));
    });

    it('filters members by search term', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        memberWithRole(SystemRole::Sales, $company, User::factory()->create(['name' => 'Zoe Finder']));

        $this->actingAs($admin)
            ->get(route('team.members.index', ['search' => 'zoe']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.user.name', 'Zoe Finder'));
    });

    it('is forbidden without users.manage', function () {
        [$employee] = memberWithRole(SystemRole::Employee);

        $this->actingAs($employee)->get(route('team.members.index'))->assertForbidden();
    });
});

describe('roles', function () {
    it('updates the roles of a member', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        [$member] = memberWithRole(SystemRole::Employee, $company);
        $membership = $member->membershipIn($company);

        $this->actingAs($admin)
            ->put(route('team.members.roles', $membership), ['role_ids' => roleIds($company, 'sales', 'warehouse')])
            ->assertSessionHasNoErrors();

        expect($membership->fresh()->roles->pluck('slug')->sort()->values()->all())->toBe(['sales', 'warehouse']);
    });

    it('does not let members change their own roles', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);

        $this->actingAs($admin)
            ->put(route('team.members.roles', $admin->membershipIn($company)), ['role_ids' => roleIds($company, 'owner')])
            ->assertSessionHasErrors('rule');
    });

    it('does not let an administrator grant ownership', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        [$member] = memberWithRole(SystemRole::Employee, $company);

        $this->actingAs($admin)
            ->put(route('team.members.roles', $member->membershipIn($company)), ['role_ids' => roleIds($company, 'owner')])
            ->assertSessionHasErrors('rule');
    });

    it('lets an owner transfer ownership', function () {
        [$owner, $company] = memberWithRole(SystemRole::Owner);
        [$member] = memberWithRole(SystemRole::Employee, $company);

        $this->actingAs($owner)
            ->put(route('team.members.roles', $member->membershipIn($company)), ['role_ids' => roleIds($company, 'owner')])
            ->assertSessionHasNoErrors();

        expect($member->membershipIn($company)->isOwner())->toBeTrue();
    });

    it('never removes the last owner', function () {
        [$owner, $company] = memberWithRole(SystemRole::Owner);
        $superAdmin = User::factory()->superAdmin()->create();
        memberWithRole(SystemRole::Employee, $company, $superAdmin);

        $this->actingAs($superAdmin)
            ->put(route('team.members.roles', $owner->membershipIn($company)), ['role_ids' => roleIds($company, 'employee')])
            ->assertSessionHasErrors('rule');

        expect($owner->membershipIn($company)->isOwner())->toBeTrue();
    });

    it('rejects roles from another company', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        [$member] = memberWithRole(SystemRole::Employee, $company);
        [, $other] = memberWithRole(SystemRole::Owner);

        $this->actingAs($admin)
            ->put(route('team.members.roles', $member->membershipIn($company)), ['role_ids' => roleIds($other, 'administrator')])
            ->assertSessionHasErrors('role_ids.0');
    });
});

describe('status', function () {
    it('suspends a member in this company only', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        [$member] = memberWithRole(SystemRole::Sales, $company);
        [, $otherCompany] = memberWithRole(SystemRole::Sales, user: $member);

        $this->actingAs($admin)
            ->post(route('team.members.suspend', $member->membershipIn($company)))
            ->assertSessionHasNoErrors();

        expect($member->membershipIn($company)->status)->toBe(MembershipStatus::Suspended)
            ->and($member->membershipIn($otherCompany)->status)->toBe(MembershipStatus::Active)
            ->and($member->fresh()->isActive())->toBeTrue();
    });

    it('cuts access of a suspended member immediately', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        [$member] = memberWithRole(SystemRole::Sales, $company);

        $this->actingAs($admin)->post(route('team.members.suspend', $member->membershipIn($company)));

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.company.create'));
    });

    it('reactivates a suspended member', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);
        [$member] = memberWithRole(SystemRole::Sales, $company);
        $company->users()->updateExistingPivot($member->id, ['status' => 'suspended']);

        $this->actingAs($admin)
            ->post(route('team.members.reactivate', $member->membershipIn($company)))
            ->assertSessionHasNoErrors();

        expect($member->membershipIn($company)->status)->toBe(MembershipStatus::Active);
    });

    it('does not let an administrator suspend an owner', function () {
        [$owner, $company] = memberWithRole(SystemRole::Owner);
        [$admin] = memberWithRole(SystemRole::Administrator, $company);

        $this->actingAs($admin)
            ->post(route('team.members.suspend', $owner->membershipIn($company)))
            ->assertSessionHasErrors('rule');
    });

    it('does not let members suspend themselves', function () {
        [$admin, $company] = memberWithRole(SystemRole::Administrator);

        $this->actingAs($admin)
            ->post(route('team.members.suspend', $admin->membershipIn($company)))
            ->assertSessionHasErrors('rule');
    });
});

describe('tenant isolation', function () {
    it('returns 404 when targeting a membership of another company', function (string $route, string $method) {
        [$admin] = memberWithRole(SystemRole::Administrator);
        [$victim, $otherCompany] = memberWithRole(SystemRole::Sales);
        $foreign = $victim->membershipIn($otherCompany);

        $payload = $route === 'team.members.roles' ? ['role_ids' => roleIds($otherCompany, 'sales')] : [];

        $this->actingAs($admin)
            ->{$method}(route($route, $foreign), $payload)
            ->assertNotFound();

        expect(Membership::find($foreign->id)->status)->toBe(MembershipStatus::Active);
    })->with([
        'update roles' => ['team.members.roles', 'put'],
        'suspend' => ['team.members.suspend', 'post'],
        'reactivate' => ['team.members.reactivate', 'post'],
    ]);
});
