<?php

use App\Exceptions\MissingTenantContext;
use App\Exceptions\TenantMismatch;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\FixtureProject;
use Tests\Fixtures\FixtureTask;
use Tests\Fixtures\TenantFixtures;

beforeEach(function () {
    TenantFixtures::migrate();

    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
});

it('only returns records of the active company', function () {
    tenant()->run($this->companyA, fn () => FixtureProject::create(['name' => 'A project']));
    tenant()->run($this->companyB, fn () => FixtureProject::create(['name' => 'B project']));

    actAsCompany($this->companyA);

    expect(FixtureProject::pluck('name')->all())->toBe(['A project']);
});

it('cannot find a record of another company by id', function () {
    $foreign = tenant()->run($this->companyB, fn () => FixtureProject::create(['name' => 'B project']));

    actAsCompany($this->companyA);

    expect(FixtureProject::find($foreign->id))->toBeNull();
});

it('fills company_id from the active company on create', function () {
    actAsCompany($this->companyA);

    $project = FixtureProject::create(['name' => 'Auto-scoped']);

    expect($project->company_id)->toBe($this->companyA->id);
});

it('fails closed when no company is active', function () {
    FixtureProject::query()->get();
})->throws(MissingTenantContext::class);

it('refuses to create records for a company other than the active one', function () {
    actAsCompany($this->companyA);

    FixtureProject::create(['name' => 'Smuggled', 'company_id' => $this->companyB->id]);
})->throws(TenantMismatch::class);

it('does not allow moving a record to another company', function () {
    actAsCompany($this->companyA);
    $project = FixtureProject::create(['name' => 'Mine']);

    $project->company_id = $this->companyB->id;
    $project->save();
})->throws(TenantMismatch::class);

it('rejects cross-company references at the database level', function () {
    $foreignProject = tenant()->run($this->companyB, fn () => FixtureProject::create(['name' => 'B project']));

    // Bypass Eloquent entirely: the composite foreign key must still hold.
    DB::table('fixture_tasks')->insert([
        'company_id' => $this->companyA->id,
        'project_id' => $foreignProject->id,
        'title' => 'Points at company B',
    ]);
})->throws(QueryException::class);

it('accepts same-company references at the database level', function () {
    actAsCompany($this->companyA);
    $project = FixtureProject::create(['name' => 'A project']);

    $task = FixtureTask::create(['project_id' => $project->id, 'title' => 'Valid']);

    expect($task->project->is($project))->toBeTrue();
});

it('restores the previous company after run()', function () {
    actAsCompany($this->companyA);

    tenant()->run($this->companyB, fn () => expect(tenant()->id())->toBe($this->companyB->id));

    expect(tenant()->id())->toBe($this->companyA->id);
});

it('restores the previous company even when the callback throws', function () {
    actAsCompany($this->companyA);

    try {
        tenant()->run($this->companyB, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect(tenant()->id())->toBe($this->companyA->id);
});

it('can explicitly bypass tenancy for platform-level code', function () {
    tenant()->run($this->companyA, fn () => FixtureProject::create(['name' => 'A']));
    tenant()->run($this->companyB, fn () => FixtureProject::create(['name' => 'B']));

    $count = tenant()->withoutTenancy(fn () => FixtureProject::count());

    expect($count)->toBe(2)
        ->and(tenant()->isBypassed())->toBeFalse();
});
