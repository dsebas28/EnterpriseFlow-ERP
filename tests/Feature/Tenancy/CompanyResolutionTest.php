<?php

use App\Enums\MembershipStatus;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\FixtureProject;
use Tests\Fixtures\TenantFixtures;

beforeEach(function () {
    TenantFixtures::migrate();

    // Test-only routes that exercise the real middleware stack.
    Route::middleware(['web', 'auth', 'tenant'])
        ->get('/_test/projects/{project}', fn (FixtureProject $project) => $project->name);

    Route::middleware(['api', 'auth:sanctum', 'tenant'])
        ->get('/api/_test/projects', fn () => FixtureProject::pluck('name'));
});

describe('web', function () {
    it('sends users without a company to onboarding', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.company.create'));
    });

    it('resolves the default company and shares it with the frontend', function () {
        [$user, $company] = companyWithMember();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas(ResolveCurrentCompany::SESSION_KEY, $company->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.current.id', $company->id)
                ->has('tenant.companies', 1));
    });

    it('lets a member switch between their companies', function () {
        [$user] = companyWithMember();
        $second = Company::factory()->withMember($user)->create();

        $this->actingAs($user)
            ->post(route('companies.switch', $second))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.current.id', $second->id));
    });

    it('returns 404 when switching to a company the user does not belong to', function () {
        [$user] = companyWithMember();
        $foreign = Company::factory()->create();

        $this->actingAs($user)
            ->post(route('companies.switch', $foreign))
            ->assertNotFound();
    });

    it('returns 404 for a record of another company via route model binding', function () {
        [$user, $company] = companyWithMember();
        $foreign = Company::factory()->create();

        $foreignProject = tenant()->run($foreign, fn () => FixtureProject::create(['name' => 'Secret']));
        $ownProject = tenant()->run($company, fn () => FixtureProject::create(['name' => 'Mine']));
        tenant()->set(null);

        $this->actingAs($user)->get("/_test/projects/{$ownProject->id}")->assertOk()->assertSee('Mine');
        $this->actingAs($user)->get("/_test/projects/{$foreignProject->id}")->assertNotFound();
    });

    it('revokes access immediately when the membership is suspended', function () {
        [$user, $company] = companyWithMember();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $company->users()->updateExistingPivot($user->id, ['status' => MembershipStatus::Suspended->value]);

        $this->get(route('dashboard'))->assertRedirect(route('onboarding.company.create'));
    });

    it('does not resolve suspended companies', function () {
        $user = User::factory()->create();
        Company::factory()->suspended()->withMember($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.company.create'));
    });

    it('falls back to a valid company when the session points to a foreign one', function () {
        [$user, $company] = companyWithMember();
        $foreign = Company::factory()->create();

        $this->actingAs($user)
            ->withSession([ResolveCurrentCompany::SESSION_KEY => $foreign->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas(ResolveCurrentCompany::SESSION_KEY, $company->id);
    });
});

describe('api', function () {
    it('uses the company sent in the X-Company-Id header', function () {
        [$user, $companyA] = companyWithMember();
        $companyB = Company::factory()->withMember($user)->create();

        tenant()->run($companyA, fn () => FixtureProject::create(['name' => 'A']));
        tenant()->run($companyB, fn () => FixtureProject::create(['name' => 'B']));
        tenant()->set(null);

        Sanctum::actingAs($user);

        $this->getJson('/api/_test/projects', [ResolveCurrentCompany::HEADER => $companyB->id])
            ->assertOk()
            ->assertExactJson(['B']);
    });

    it('rejects a header pointing to a company the user does not belong to', function () {
        [$user] = companyWithMember();
        $foreign = Company::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/_test/projects', [ResolveCurrentCompany::HEADER => $foreign->id])
            ->assertForbidden()
            ->assertJson(['success' => false]);
    });

    it('rejects users without any company', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/_test/projects')->assertForbidden();
    });
});
