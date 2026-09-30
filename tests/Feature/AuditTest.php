<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    [$this->manager, $this->company] = memberWithRole(SystemRole::Manager);
    [$this->owner] = memberWithRole(SystemRole::Owner, $this->company);
});

function auditEntries(object $test, ?string $event = null): Illuminate\Support\Collection
{
    return tenant()->run($test->company, fn () => AuditLog::query()
        ->when($event, fn ($q) => $q->where('event', $event))
        ->orderBy('id')
        ->get());
}

it('records only the attributes that changed, with before and after values', function () {
    $product = tenant()->run($this->company, fn () => Product::factory()->create(['price' => 50000, 'name' => 'Chair']));

    $this->actingAs($this->manager)
        ->withHeader('User-Agent', 'AuditTest/1.0')
        ->put(route('catalog.products.update', $product), [
            'name' => 'Chair', 'sku' => $product->sku, 'barcode' => $product->barcode, 'cost' => '100',
            'price' => '450', 'tax_rate' => '0', 'min_stock' => 0, 'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    $entry = auditEntries($this, 'updated')->where('auditable_id', $product->id)->sole();

    expect($entry->auditable_type)->toBe('product')
        ->and($entry->user_id)->toBe($this->manager->id)
        ->and($entry->company_id)->toBe($this->company->id)
        ->and($entry->old_values)->toMatchArray(['price' => 50000])
        ->and($entry->new_values)->toMatchArray(['price' => 45000])
        ->and($entry->new_values)->not->toHaveKey('name')        // unchanged
        ->and($entry->new_values)->not->toHaveKey('updated_at')  // never recorded
        ->and($entry->ip_address)->toBe('127.0.0.1')
        ->and($entry->user_agent)->toBe('AuditTest/1.0')
        ->and($entry->method)->toBe('PUT');
});

it('records creation, soft deletion and restoration', function () {
    tenant()->run($this->company, function () {
        $product = Product::factory()->create();
        $product->delete();
        $product->restore();
    });

    expect(auditEntries($this)->where('auditable_type', 'product')->pluck('event')->all())
        ->toBe(['created', 'deleted', 'restored']);
});

it('skips updates that only touch excluded attributes', function () {
    $product = tenant()->run($this->company, fn () => Product::factory()->create());
    $before = auditEntries($this)->count();

    tenant()->run($this->company, fn () => $product->touch());

    expect(auditEntries($this)->count())->toBe($before);
});

it('never stores secrets or timestamps', function () {
    $product = tenant()->run($this->company, fn () => Product::factory()->make());

    $values = (fn () => $this->auditableValues([
        'password' => 'hunter2', 'remember_token' => 'r', 'token_hash' => 'h',
        'updated_at' => 'now', 'price' => 100,
    ]))->call($product);

    expect($values)->toBe(['price' => 100]);
});

it('records role permission changes and member role changes as explicit events', function () {
    [$employee] = memberWithRole(SystemRole::Employee, $this->company);
    $sales = tenant()->run($this->company, fn () => Role::firstWhere('slug', 'sales'));

    $this->actingAs($this->owner)
        ->put(route('team.roles.update', $sales), ['name' => 'Sales', 'permissions' => ['sales.view', 'reports.view']])
        ->assertSessionHasNoErrors();

    $roleChange = auditEntries($this, 'role.permissions_changed')->sole();
    expect($roleChange->new_values['permissions'])->toBe(['reports.view'])
        ->and($roleChange->old_values['permissions'])->toContain('sales.create');

    $this->actingAs($this->owner)
        ->put(route('team.members.roles', $employee->membershipIn($this->company)), ['role_ids' => [$sales->id]])
        ->assertSessionHasNoErrors();

    $memberChange = auditEntries($this, 'member.roles_changed')->sole();
    expect($memberChange->old_values['roles'])->toBe(['Employee'])
        ->and($memberChange->new_values['roles'])->toBe(['Sales']);
});

it('leaves no audit entry when the operation is rolled back', function () {
    $before = auditEntries($this)->count();

    try {
        DB::transaction(function () {
            tenant()->run($this->company, fn () => Product::factory()->create());
            throw new RuntimeException('failed later');
        });
    } catch (RuntimeException) {
    }

    expect(auditEntries($this)->count())->toBe($before);
});

it('is append-only in the model and in the database', function () {
    tenant()->run($this->company, fn () => Product::factory()->create());
    $entry = auditEntries($this)->last();
    $original = $entry->event;

    expect(fn () => $entry->forceFill(['event' => 'tampered'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        // Savepoint per statement: PostgreSQL aborts the enclosing transaction on error.
        ->and(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['event' => 'tampered'])))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->delete()))->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_logs')->where('id', $entry->id)->value('event'))->toBe($original);
});

it('records system actions without a user', function () {
    tenant()->run($this->company, fn () => Product::factory()->create());

    expect(auditEntries($this)->last()->user_id)->toBeNull();
});

it('shows the audit trail with filters to members with audit.view', function () {
    $product = tenant()->run($this->company, fn () => Product::factory()->create());

    $this->actingAs($this->owner)
        ->get(route('audit.index', ['type' => 'product', 'id' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'created')
            ->where('logs.data.0.type', 'product'));

    $this->actingAs($this->manager)->get(route('audit.index'))->assertForbidden();
});

it('never shows audit entries of another company', function () {
    [$outsider, $other] = memberWithRole(SystemRole::Owner);
    tenant()->run($other, fn () => Product::factory()->create(['name' => 'Secret']));
    tenant()->run($this->company, fn () => Product::factory()->create());

    $this->actingAs($this->owner)
        ->get(route('audit.index', ['type' => 'product']))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));

    expect(tenant()->run($other, fn () => AuditLog::where('auditable_type', 'product')->count()))->toBe(1);
});

it('records business state changes such as a cancelled sale', function () {
    $sale = tenant()->run($this->company, function () {
        $this->actingAs($this->manager);

        return app(App\Actions\Sales\SaveSale::class)->handle(null, new App\DTOs\SaleData(
            App\Models\Customer::factory()->create()->id,
            App\Models\Warehouse::factory()->create()->id,
            '2026-09-29',
            null,
            [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_price' => 100, 'discount_rate' => 0, 'tax_rate' => 0]],
        ), $this->manager);
    });

    $this->actingAs($this->owner)->post(route('sales.orders.cancel', $sale), ['reason' => 'Duplicate order'])->assertSessionHasNoErrors();

    $entry = auditEntries($this, 'updated')->where('auditable_type', 'sale')->last();
    expect($entry->old_values['status'])->toBe('draft')
        ->and($entry->new_values)->toMatchArray(['status' => 'cancelled', 'cancel_reason' => 'Duplicate order', 'cancelled_by' => $this->owner->id])
        ->and($entry->user_id)->toBe($this->owner->id);
});
