<?php

use App\Enums\SystemRole;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BusinessScenario;

/*
| Every page of the application renders for an Owner over a realistic data
| set, and the Vue component it names exists. Catches broken props, missing
| eager loads (lazy loading is strict outside production) and typos in
| component names on pages that have no dedicated test yet.
*/

/** GET routes that do not answer with an Inertia page. */
const NON_PAGE_ROUTES = [
    'catalog.products.lookup',   // JSON for the product picker
    'notifications.unread-count', // JSON for the bell
    'notifications.open',         // redirect
    'finance.invoices.pdf',       // file download
    'finance.expenses.receipt',   // file download
    'reports.exports.download',   // file download
];

it('renders every tenant page for an owner', function () {
    $this->travelTo('2026-09-29 10:00:00');
    [$owner, $company] = memberWithRole(SystemRole::Owner);
    $s = BusinessScenario::build($company, $owner);

    // Edit pages need records that are still editable.
    $params = [
        'product' => $s->chair->id,
        'customer' => $s->customer->id,
        'sale' => $s->draftSale->id,
        'purchase_order' => $s->draftOrder->id,
        'invoice' => $s->invoice->id,
        'bill' => $s->bill->id,
        'key' => 'sales-by-period',
    ];

    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (RouteDefinition $route) => in_array('GET', $route->methods(), true)
        && in_array('tenant', $route->gatherMiddleware(), true)
        && ! str_starts_with($route->uri(), 'api/')
        && ! in_array($route->getName(), NON_PAGE_ROUTES, true));

    $rendered = [];
    foreach ($routes as $route) {
        $url = route($route->getName(), array_intersect_key($params, array_flip($route->parameterNames())));

        $response = $this->actingAs($owner)->get($url);
        expect($response->status())->toBe(200, "{$route->getName()} answered {$response->status()}");

        $component = $response->viewData('page')['component'];
        $response->assertInertia(fn (Assert $page) => $page->component($component));
        $rendered[] = $route->getName();
    }

    expect(count($rendered))->toBeGreaterThan(25);
});

it('renders every report with data', function (string $key) {
    $this->travelTo('2026-09-29 10:00:00');
    [$owner, $company] = memberWithRole(SystemRole::Owner);
    BusinessScenario::build($company, $owner);

    $this->actingAs($owner)
        ->get(route('reports.show', ['key' => $key, 'from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/Show')->where('report.key', $key)->has('rows'));
})->with(fn () => array_map(
    fn ($report) => $report->key(),
    app(App\Reports\ReportRegistry::class)->all(),
));
