<?php

namespace App\Support\Finance;

use App\Enums\PaymentMethod;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;

/**
 * Options the payment dialog needs, shared by invoice and bill pages.
 */
final class PaymentFormOptions
{
    /**
     * @return array{methods: list<array{value: string, label: string}>, today: string, currency: array{code: string, decimals: int}}
     */
    public static function make(): array
    {
        $company = app(TenantContext::class)->companyOrFail();

        return [
            'methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'today' => now($company->timezone)->toDateString(),
            'currency' => ['code' => $company->currency, 'decimals' => Currency::minorUnits($company->currency)],
        ];
    }
}
