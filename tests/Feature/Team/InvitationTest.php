<?php

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Invite through the HTTP endpoint and capture the plain token from the
 * notification (it is never stored in the database).
 */
function inviteViaHttp(User $admin, string $email, string $roleSlug = 'sales'): string
{
    Notification::fake();

    $roleId = tenant()->run(
        $admin->activeCompanies()->first(),
        fn () => Role::firstWhere('slug', $roleSlug)->id,
    );

    test()->actingAs($admin)
        ->post(route('team.invitations.store'), ['email' => $email, 'role_id' => $roleId])
        ->assertSessionHasNoErrors();

    $token = null;
    $capture = function (CompanyInvitation $n) use (&$token) {
        $token = basename($n->url);

        return true;
    };

    // Existing accounts are notified as users (mail + bell); anyone else by email only.
    $existing = User::firstWhere('email', $email);
    $existing !== null
        ? Notification::assertSentTo($existing, CompanyInvitation::class, fn (CompanyInvitation $n, array $channels) => $channels === ['mail', 'database'] && $capture($n))
        : Notification::assertSentTo(new AnonymousNotifiable, CompanyInvitation::class, fn (CompanyInvitation $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $email && $capture($n));

    auth()->logout();

    return $token;
}

it('sends an invitation email and stores only a hash of the token', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);

    $token = inviteViaHttp($admin, 'new.hire@example.com');

    $invitation = tenant()->withoutTenancy(fn () => Invitation::sole());
    expect($invitation->getRawOriginal('token_hash'))->toBe(hash('sha256', $token))
        ->and($invitation->getRawOriginal('token_hash'))->not->toBe($token);
});

it('forbids inviting without users.manage', function () {
    [$sales, $company] = memberWithRole(SystemRole::Sales);
    $roleId = tenant()->run($company, fn () => Role::firstWhere('slug', 'employee')->id);

    $this->actingAs($sales)
        ->post(route('team.invitations.store'), ['email' => 'x@example.com', 'role_id' => $roleId])
        ->assertForbidden();
});

it('rejects a role that belongs to another company', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    [, $other] = memberWithRole(SystemRole::Owner);
    $foreignRoleId = tenant()->run($other, fn () => Role::firstWhere('slug', 'sales')->id);

    $this->actingAs($admin)
        ->post(route('team.invitations.store'), ['email' => 'x@example.com', 'role_id' => $foreignRoleId])
        ->assertSessionHasErrors('role_id');
});

it('does not allow a non-owner to invite owners', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    Notification::fake();

    $ownerRoleId = tenant()->run($admin->activeCompanies()->first(), fn () => Role::firstWhere('slug', 'owner')->id);

    $this->actingAs($admin)
        ->post(route('team.invitations.store'), ['email' => 'boss@example.com', 'role_id' => $ownerRoleId])
        ->assertSessionHasErrors('rule');

    Notification::assertNothingSent();
});

it('does not invite someone who is already a member', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    $existing = User::factory()->create();
    memberWithRole(SystemRole::Sales, $company, $existing);

    $roleId = tenant()->run($company, fn () => Role::firstWhere('slug', 'sales')->id);

    $this->actingAs($admin)
        ->post(route('team.invitations.store'), ['email' => strtoupper($existing->email), 'role_id' => $roleId])
        ->assertSessionHasErrors('rule');
});

it('lets a new person create an account and join with the invited role', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    $token = inviteViaHttp($admin, 'new.hire@example.com', 'warehouse');

    $this->get(route('invitations.show', $token))->assertOk();

    $this->post(route('invitations.accept', $token), [
        'name' => 'New Hire',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertRedirect(route('dashboard'));

    $user = User::firstWhere('email', 'new.hire@example.com');
    $this->assertAuthenticatedAs($user);

    $membership = $user->membershipIn($company);
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->roles->pluck('slug')->all())->toBe(['warehouse'])
        ->and($user->email_verified_at)->not->toBeNull();
});

it('lets an existing user of another company accept while signed in', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    [$outsider] = memberWithRole(SystemRole::Owner);
    $token = inviteViaHttp($admin, $outsider->email);

    $this->actingAs($outsider)
        ->post(route('invitations.accept', $token))
        ->assertRedirect(route('dashboard'));

    expect($outsider->activeCompanies()->count())->toBe(2)
        ->and($outsider->membershipIn($company)->roles->pluck('slug')->all())->toBe(['sales']);
});

it('sends existing users to log in first', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    $existing = User::factory()->create();
    $token = inviteViaHttp($admin, $existing->email);

    $this->post(route('invitations.accept', $token))->assertRedirect(route('login'));

    expect($existing->companies()->count())->toBe(0);
});

it('refuses acceptance by a user with a different email', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    $token = inviteViaHttp($admin, 'invitee@example.com');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('invitations.accept', $token))
        ->assertSessionHasErrors('rule');

    expect($intruder->companies()->count())->toBe(0);
});

it('can only be used once', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    $token = inviteViaHttp($admin, 'once@example.com');
    $payload = ['name' => 'Once', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'];

    $this->post(route('invitations.accept', $token), $payload)->assertRedirect();
    auth()->logout();

    $this->post(route('invitations.accept', $token), $payload)->assertNotFound();
    $this->get(route('invitations.show', $token))->assertNotFound();
});

it('expires', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    $token = inviteViaHttp($admin, 'late@example.com');

    $this->travel(Invitation::TTL_DAYS + 1)->days();

    $this->get(route('invitations.show', $token))->assertNotFound();
});

it('returns 404 for unknown tokens', function () {
    $this->get(route('invitations.show', str_repeat('x', 64)))->assertNotFound();
});

it('revokes the previous pending invitation when re-inviting', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    $first = inviteViaHttp($admin, 'twice@example.com');
    $second = inviteViaHttp($admin, 'twice@example.com');

    $this->get(route('invitations.show', $first))->assertNotFound();
    $this->get(route('invitations.show', $second))->assertOk();
});

it('cannot revoke an invitation of another company', function () {
    [$adminA] = memberWithRole(SystemRole::Administrator);
    [$adminB] = memberWithRole(SystemRole::Administrator);
    inviteViaHttp($adminB, 'b-invitee@example.com');
    $foreign = tenant()->withoutTenancy(fn () => Invitation::sole());

    $this->actingAs($adminA)
        ->delete(route('team.invitations.destroy', $foreign->id))
        ->assertNotFound();

    expect(tenant()->withoutTenancy(fn () => $foreign->fresh()->revoked_at))->toBeNull();
});

it('queues the invitation email without needing a tenant context', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    actAsCompany($company);
    $invitation = new Invitation(['email' => 'q@example.com', 'role_id' => Role::firstWhere('slug', 'sales')->id]);
    $invitation->forceFill(['token_hash' => Invitation::hashToken('plain-token'), 'invited_by' => $admin->id, 'expires_at' => now()->addDay()])->save();

    $notification = new CompanyInvitation($invitation, 'plain-token');
    tenant()->set(null);

    // Simulate the worker: unserialize and render with no active company.
    $restored = unserialize(serialize($notification));
    $mail = $restored->toMail(new AnonymousNotifiable);

    expect($mail->actionUrl)->toBe(route('invitations.show', 'plain-token'))
        ->and($mail->subject)->toContain($company->name);

    // Existing users also get it in their bell, as a personal notification.
    $invitee = User::factory()->create();
    expect($restored->via($invitee))->toBe(['mail', 'database'])
        ->and($restored->via(new AnonymousNotifiable))->toBe(['mail'])
        ->and($restored->databaseType($invitee))->toBe('invitation')
        ->and($restored->toArray($invitee))->toMatchArray([
            'category' => 'invitation',
            'title' => "You're invited to join {$company->name}",
            'url' => route('invitations.show', 'plain-token'),
        ]);
});
