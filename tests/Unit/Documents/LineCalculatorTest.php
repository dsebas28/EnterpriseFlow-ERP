<?php

use App\Enums\PurchaseOrderStatus as S;
use App\Support\Documents\LineCalculator;
use App\Support\Documents\WeightedAverageCost;

it('computes a line with tax rounded half-up', function () {
    // 3 × 33.33 = 99.99; 19% = 18.9981 -> 19.00
    $line = LineCalculator::line(quantity: 3, unitPrice: 3333, taxRate: 1900);

    expect($line->subtotal)->toBe(9999)
        ->and($line->tax)->toBe(1900)
        ->and($line->total)->toBe(11899)
        ->and($line->discount)->toBe(0);
});

it('applies discounts before tax', function () {
    // 2 × 100.00 = 200.00; -10% = 180.00; +19% = 34.20
    $line = LineCalculator::line(quantity: 2, unitPrice: 10000, taxRate: 1900, discountRate: 1000);

    expect($line->gross)->toBe(20000)
        ->and($line->discount)->toBe(2000)
        ->and($line->subtotal)->toBe(18000)
        ->and($line->tax)->toBe(3420)
        ->and($line->total)->toBe(21420);
});

it('sums document totals from rounded lines', function () {
    $lines = [
        LineCalculator::line(1, 1001, 1900), // tax 190.19 -> 190
        LineCalculator::line(1, 1001, 1900),
    ];

    // Per-line rounding: document tax is exactly the sum of line taxes.
    expect(LineCalculator::totals($lines))->toBe(['subtotal' => 2002, 'discount' => 0, 'tax' => 380, 'total' => 2382]);
});

it('rejects invalid line values', function (int $qty, int $price, int $tax, int $discount) {
    LineCalculator::line($qty, $price, $tax, $discount);
})->throws(InvalidArgumentException::class)->with([
    'zero quantity' => [0, 100, 0, 0],
    'negative price' => [1, -1, 0, 0],
    'discount over 100%' => [1, 100, 0, 10_001],
]);

it('calculates the moving weighted average cost', function (int $onHand, int $cost, int $qty, int $newCost, int $expected) {
    expect(WeightedAverageCost::calculate($onHand, $cost, $qty, $newCost))->toBe($expected);
})->with([
    'blend' => [10, 1000, 10, 2000, 1500],
    'weighted' => [30, 1000, 10, 2000, 1250],
    'rounding half-up' => [2, 1000, 1, 1001, 1000], // 1000.33
    'rounds up' => [1, 1000, 1, 1001, 1001],        // 1000.5
    'no stock on hand' => [0, 1000, 5, 3000, 3000],
    'negative stock' => [-3, 1000, 5, 3000, 3000],
]);

it('only allows the documented purchase order transitions', function () {
    $allowed = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['approved', 'draft', 'cancelled'],
        'approved' => ['partially_received', 'received', 'cancelled'],
        'partially_received' => ['partially_received', 'received'],
        'received' => [],
        'cancelled' => [],
    ];

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            expect($from->canTransitionTo($to))->toBe(in_array($to->value, $allowed[$from->value], true), "{$from->value} -> {$to->value}");
        }
    }
});
