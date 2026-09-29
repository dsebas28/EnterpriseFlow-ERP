<?php

use App\DTOs\CompanyData;

it('normalises country and currency codes', function () {
    $data = CompanyData::fromArray([
        'name' => 'Acme',
        'country' => 'co',
        'currency' => 'cop',
        'timezone' => 'America/Bogota',
    ]);

    expect($data->country)->toBe('CO')
        ->and($data->currency)->toBe('COP')
        ->and($data->taxId)->toBeNull();
});

it('maps to model attributes', function () {
    $data = new CompanyData(name: 'Acme', country: 'CO', currency: 'COP', timezone: 'UTC', taxId: '123');

    expect($data->toAttributes())
        ->toMatchArray(['name' => 'Acme', 'tax_id' => '123', 'legal_name' => null]);
});
