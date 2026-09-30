<?php

use App\Enums\SystemRole;
use App\Models\Supplier;
use Tests\Support\BusinessScenario;

beforeEach(function () {
    $this->travelTo('2026-09-29 10:00:00');
    [$this->owner, $this->company] = memberWithRole(SystemRole::Owner);
    [$this->accountant] = memberWithRole(SystemRole::Accountant, $this->company);
    $this->s = BusinessScenario::build($this->company, $this->owner);
});

function purchasingReport(object $test, string $key, array $filters = []): array
{
    return test()->actingAs($test->accountant)
        ->getJson(route('api.v1.reports.show', ['key' => $key, ...$filters]), ['X-Company-Id' => $test->company->id])
        ->assertOk()
        ->json('data');
}

it('totals approved purchase orders per period, ignoring drafts', function () {
    // Approved order: 4 desks × 200.00 = 800.00 + 19% tax. The draft order
    // for the same amount must not count.
    $report = purchasingReport($this, 'purchases-by-period', ['from' => '2026-09-01', 'to' => '2026-09-30', 'group_by' => 'month']);

    expect($report['rows'])->toBe([
        ['period' => '2026-09', 'orders' => 1, 'net' => 80000, 'tax' => 15200, 'total' => 95200],
    ])->and($report['totals']['total'])->toBe(95200);
});

it('filters purchases by supplier and period', function () {
    $other = tenant()->run($this->company, fn () => Supplier::factory()->create());

    expect(purchasingReport($this, 'purchases-by-period', ['from' => '2026-09-01', 'to' => '2026-09-30', 'supplier_id' => $other->id])['rows'])->toBe([])
        ->and(purchasingReport($this, 'purchases-by-period', ['from' => '2026-08-01', 'to' => '2026-08-31'])['rows'])->toBe([]);
});

it('ages what is owed to suppliers by days past due', function () {
    // Bill: 3 × 200.00 + 19% = 714.00, due 2026-10-06.
    $current = purchasingReport($this, 'payables-aging');
    expect($current['rows'])->toBe([
        ['party' => 'Acme Supplies', 'current' => 71400, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_plus' => 0, 'total' => 71400],
    ]);

    $this->travelTo('2026-11-20 10:00:00'); // 45 days late
    $late = purchasingReport($this, 'payables-aging');
    expect($late['rows'][0])->toMatchArray(['current' => 0, 'd31_60' => 71400, 'total' => 71400]);

    $this->travelTo('2027-03-01 10:00:00'); // 146 days late
    expect(purchasingReport($this, 'payables-aging')['rows'][0])->toMatchArray(['d90_plus' => 71400]);
});
