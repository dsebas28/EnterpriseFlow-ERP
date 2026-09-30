<?php

use App\Support\Money\BasisPoints;
use App\Support\Money\Currency;
use App\Support\Money\Money;

it('parses decimals into minor units without floats', function (string $input, string $currency, int $minor) {
    expect(Money::fromDecimal($input, $currency)->minor)->toBe($minor);
})->with([
    ['123.45', 'USD', 12345],
    ['123.4', 'USD', 12340],
    ['123', 'USD', 12300],
    ['0.1', 'USD', 10],
    ['1500', 'CLP', 1500],
    ['1.234', 'KWD', 1234],
    ['-5.50', 'USD', -550],
    // 0.1 + 0.2 style inputs never go through binary floating point.
    ['19999999.99', 'COP', 1999999999],
]);

it('rejects malformed amounts and excess precision', function (string $input, string $currency) {
    Money::fromDecimal($input, $currency);
})->throws(InvalidArgumentException::class)->with([
    ['12.345', 'USD'],
    ['10.5', 'CLP'],
    ['1,000.00', 'USD'],
    ['abc', 'USD'],
    ['', 'USD'],
]);

it('renders minor units back to a decimal string', function (int $minor, string $currency, string $decimal) {
    expect((new Money($minor, $currency))->toDecimal())->toBe($decimal);
})->with([
    [12345, 'USD', '123.45'],
    [5, 'USD', '0.05'],
    [0, 'USD', '0.00'],
    [-550, 'USD', '-5.50'],
    [1500, 'CLP', '1500'],
    [1234, 'KWD', '1.234'],
]);

it('computes percentages with half-up rounding', function (int $minor, int $basisPoints, int $expected) {
    expect((new Money($minor, 'USD'))->percentage($basisPoints)->minor)->toBe($expected);
})->with([
    'exact' => [10000, 1900, 1900],
    'round down' => [1234, 1900, 234],   // 234.46
    'round half up' => [250, 1000, 25],  // 25.0
    'half' => [5, 1000, 1],              // 0.5 -> 1
    'negative half' => [-5, 1000, -1],   // -0.5 -> -1
]);

it('adds and multiplies within the same currency', function () {
    $total = (new Money(1050, 'USD'))->multiply(3)->add(new Money(50, 'USD'));

    expect($total->equals(new Money(3200, 'USD')))->toBeTrue();
});

it('refuses to mix currencies', function () {
    (new Money(100, 'USD'))->add(new Money(100, 'EUR'));
})->throws(InvalidArgumentException::class);

it('knows the minor units of zero and three decimal currencies', function () {
    expect(Currency::minorUnits('clp'))->toBe(0)
        ->and(Currency::minorUnits('KWD'))->toBe(3)
        ->and(Currency::minorUnits('COP'))->toBe(2);
});

it('converts percentages to basis points and back', function () {
    expect(BasisPoints::fromPercent('19'))->toBe(1900)
        ->and(BasisPoints::fromPercent('19.5'))->toBe(1950)
        ->and(BasisPoints::fromPercent('0.25'))->toBe(25)
        ->and(BasisPoints::toPercent(1950))->toBe('19.50')
        ->and(BasisPoints::toPercent(5))->toBe('0.05');
});
