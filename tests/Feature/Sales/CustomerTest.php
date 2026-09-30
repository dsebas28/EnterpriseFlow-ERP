<?php

use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Inertia\Testing\AssertableInertia as Assert;

function customerPayload(array $overrides = []): array
{
    return [
        'kind' => 'company',
        'name' => 'Ferretería El Tornillo',
        'tax_id' => '800123123-4',
        'email' => 'compras@tornillo.test',
        'phone' => '6041234567',
        'city' => 'Cali',
        'country' => 'CO',
        'status' => 'active',
        ...$overrides,
    ];
}

it('creates, updates and searches customers', function () {
    [$user, $company] = memberWithRole(SystemRole::Sales);

    $this->actingAs($user)->post(route('sales.customers.store'), customerPayload())->assertSessionHasNoErrors();
    $customer = tenant()->run($company, fn () => Customer::sole());

    $this->actingAs($user)
        ->put(route('sales.customers.update', $customer), customerPayload(['name' => 'El Tornillo S.A.S.']))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('sales.customers.index', ['search' => 'tornillo']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'El Tornillo S.A.S.'));
});

it('enforces a unique tax id per company', function () {
    [$user] = memberWithRole(SystemRole::Sales);

    $this->actingAs($user)->post(route('sales.customers.store'), customerPayload());
    $this->actingAs($user)->post(route('sales.customers.store'), customerPayload(['name' => 'Dup']))->assertSessionHasErrors('tax_id');
});

it('shows purchase history, totals and outstanding balance', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->default()->create();
    $product = Product::factory()->create();
    app(InventoryService::class)->record(new StockMovementData($product, $warehouse, 10, StockMovementType::ManualIn));

    $sale = app(SaveSale::class)->handle(null, new SaleData($customer->id, $warehouse->id, '2026-09-29', null, [
        ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 5000, 'discount_rate' => 0, 'tax_rate' => 0],
    ]), $user);
    app(ConfirmSale::class)->handle($sale, $user);
    $sale->forceFill(['amount_paid' => 3000])->save();

    $this->get(route('sales.customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/customers/Show')
            ->where('stats.orders', 1)
            ->where('stats.sold.amount', 10000)
            ->where('stats.outstanding.amount', 7000)
            ->has('sales.data', 1));
});

it('keeps internal notes, deletable by their author', function () {
    [$user, $company] = memberWithRole(SystemRole::Sales);
    [$other] = memberWithRole(SystemRole::Employee, $company);
    $customer = tenant()->run($company, fn () => Customer::factory()->create());

    $this->actingAs($user)
        ->post(route('sales.customers.notes.store', $customer), ['body' => 'Prefers delivery on Mondays'])
        ->assertSessionHasNoErrors();

    $note = tenant()->run($company, fn () => CustomerNote::sole());
    expect($note->user_id)->toBe($user->id);

    // Another member without customers.update cannot delete someone else's note.
    $this->actingAs($other)->delete(route('sales.customers.notes.destroy', [$customer, $note]))->assertForbidden();
    $this->actingAs($user)->delete(route('sales.customers.notes.destroy', [$customer, $note]))->assertSessionHasNoErrors();
});

it('does not delete customers with open sales', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $customer = Customer::factory()->create();
    app(SaveSale::class)->handle(null, new SaleData($customer->id, Warehouse::factory()->create()->id, '2026-09-29', null, [
        ['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_price' => 100, 'discount_rate' => 0, 'tax_rate' => 0],
    ]), $user);
    [$owner] = memberWithRole(SystemRole::Owner, $company);

    $this->actingAs($owner)->delete(route('sales.customers.destroy', $customer))->assertSessionHasErrors('rule');
});

it('isolates customers and their notes between companies', function () {
    [$user] = memberWithRole(SystemRole::Manager);
    [, $other] = memberWithRole(SystemRole::Owner);
    [$foreign, $foreignNote] = tenant()->run($other, function () {
        $customer = Customer::factory()->create();
        $note = $customer->notes()->create(['body' => 'secret']);

        return [$customer, $note];
    });

    $this->actingAs($user)->get(route('sales.customers.show', $foreign))->assertNotFound();
    $this->actingAs($user)->put(route('sales.customers.update', $foreign), customerPayload())->assertNotFound();
    $this->actingAs($user)->post(route('sales.customers.notes.store', $foreign), ['body' => 'x'])->assertNotFound();
    $this->actingAs($user)->delete(route('sales.customers.notes.destroy', [$foreign, $foreignNote]))->assertNotFound();
});
